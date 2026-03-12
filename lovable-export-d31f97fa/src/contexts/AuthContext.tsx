import {
  createContext,
  useContext,
  useState,
  useCallback,
  useEffect,
  type ReactNode,
} from "react";
import {
  createUserWithEmailAndPassword,
  signInWithEmailAndPassword,
  sendEmailVerification,
  signOut as firebaseSignOut,
  onAuthStateChanged,
  type User as FirebaseUser,
} from "firebase/auth";
import { firebaseAuth } from "@/lib/firebase";
import { authApi, type User, type LoginInput, type SignupInput } from "@/lib/api/auth";
import { tokenStorage } from "@/lib/api/client";

interface AuthState {
  user: User | null;
  isLoading: boolean;
  error: string | null;
  is2FAPending: boolean;
  challengeToken: string | null;
  firebaseUser: FirebaseUser | null;
}

interface AuthContextValue extends AuthState {
  login: (data: LoginInput) => Promise<any>;
  verify2fa: (code: string) => Promise<User>;
  signup: (data: SignupInput) => Promise<User>;
  handleGoogleCallback: (code: string) => Promise<any>;
  logout: () => Promise<void>;
  clearError: () => void;
  refreshUser: () => Promise<void>;
  syncFirebaseVerification: () => Promise<boolean>;
  isAuthenticated: boolean;
  is2FAPending: boolean;
}

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [state, setState] = useState<AuthState>({
    user: null,
    isLoading: true,
    error: null,
    is2FAPending: false,
    challengeToken: null,
    firebaseUser: null,
  });

  const loadUser = useCallback(async () => {
    const token = tokenStorage.get();
    if (!token) {
      setState({ user: null, isLoading: false, error: null, is2FAPending: false, challengeToken: null, firebaseUser: null });
      return;
    }
    try {
      const user = await authApi.me();
      setState((s) => ({ ...s, user, isLoading: false, error: null, is2FAPending: false, challengeToken: null }));
    } catch {
      tokenStorage.clear();
      setState({ user: null, isLoading: false, error: null, is2FAPending: false, challengeToken: null, firebaseUser: null });
    }
  }, []);

  useEffect(() => {
    loadUser();
  }, [loadUser]);

  // Track Firebase auth state
  useEffect(() => {
    const unsubscribe = onAuthStateChanged(firebaseAuth, (fbUser) => {
      setState((s) => ({ ...s, firebaseUser: fbUser }));
    });
    return () => unsubscribe();
  }, []);

  const login = useCallback(async (data: LoginInput) => {
    setState((s) => ({ ...s, isLoading: true, error: null }));
    try {
      const response = await authApi.login(data);

      if ('requires_2fa' in response && response.requires_2fa) {
        setState((s) => ({
          ...s,
          isLoading: false,
          is2FAPending: true,
          challengeToken: response.challenge_token,
        }));
        return response;
      }

      if ('user' in response && 'token' in response) {
        const { user, token } = response;
        tokenStorage.set(token);
        
        // --- Sync Firebase Session ---
        try {
          await signInWithEmailAndPassword(firebaseAuth, data.email, data.password);
        } catch (fbErr: any) {
          console.warn("Firebase auto-sync on login failed:", fbErr.message);
        }

        setState((s) => ({ ...s, user, isLoading: false, error: null, is2FAPending: false, challengeToken: null }));
        return user;
      }

      throw new Error("Invalid response from server");
    } catch (err: any) {
      const message = err?.message ?? "Login failed. Check your credentials.";
      setState((s) => ({ ...s, isLoading: false, error: message }));
      throw err;
    }
  }, []);

  const verify2fa = useCallback(async (code: string) => {
    if (!state.challengeToken) throw new Error("No active 2FA challenge");

    setState((s) => ({ ...s, isLoading: true, error: null }));
    try {
      const response = await authApi.verify2fa({
        challenge_token: state.challengeToken,
        code,
      });

      if ('requires_2fa' in response && response.requires_2fa) {
        throw new Error("Unexpected 2FA state");
      }

      if ('user' in response && 'token' in response) {
        const { user, token } = response;
        tokenStorage.set(token);
        setState((s) => ({
          ...s,
          user,
          isLoading: false,
          is2FAPending: false,
          challengeToken: null,
        }));
        return user;
      }

      throw new Error("Invalid response from server");
    } catch (err: any) {
      const message = err?.message ?? "2FA verification failed.";
      setState((s) => ({ ...s, isLoading: false, error: message }));
      throw err;
    }
  }, [state.challengeToken]);

  const signup = useCallback(async (data: SignupInput) => {
    setState((s) => ({ ...s, isLoading: true, error: null }));
    try {
      const referralCode = localStorage.getItem("referral_code");
      const signupData = referralCode ? { ...data, referral_code: referralCode } : data;

      // 1. Create user in SaaS backend
      const response = await authApi.signup(signupData);
      if ('requires_2fa' in response && response.requires_2fa) {
        throw new Error("Registration should not redirect to 2FA challenge");
      }

      if ('user' in response && 'token' in response) {
        const { user, token } = response;
        tokenStorage.set(token);
        setState({ user, isLoading: false, error: null, is2FAPending: false, challengeToken: null, firebaseUser: null });

        if (referralCode) {
          localStorage.removeItem("referral_code");
        }

        // 2. Create or Sign In to Firebase User
        try {
          await createUserWithEmailAndPassword(firebaseAuth, data.email, data.password);
        } catch (fbErr: any) {
          if (fbErr.code === 'auth/email-already-in-use') {
            try {
              await signInWithEmailAndPassword(firebaseAuth, data.email, data.password);
            } catch (innerErr) {
              console.warn("Firebase sign-in fallback failed during signup:", innerErr);
            }
          } else {
            console.warn("Firebase signup error:", fbErr.code, fbErr.message);
          }
        }

        return user;
      }

      throw new Error("Invalid response from server");
    } catch (err: any) {
      const message = err?.message ?? "Registration failed.";
      setState((s) => ({ ...s, isLoading: false, error: message }));
      throw err;
    }
  }, []);

  /**
   * Syncs Firebase email verification status to the SaaS database.
   * Reloads Firebase user → if emailVerified → sends ID token to backend.
   * Returns true if successfully synced.
   */
  const syncFirebaseVerification = useCallback(async (): Promise<boolean> => {
    const fbUser = firebaseAuth.currentUser;
    console.log("Syncing Firebase verification. Active Firebase User:", fbUser?.email);
    
    if (!fbUser) {
      console.warn("Sync failed: No active Firebase user session.");
      return false;
    }

    try {
      console.log("Reloading Firebase user...");
      await fbUser.reload();
      const refreshedUser = firebaseAuth.currentUser;
      console.log("Firebase user reloaded. emailVerified:", refreshedUser?.emailVerified);
      
      if (!refreshedUser?.emailVerified) return false;

      console.log("Fetching Firebase ID token...");
      const idToken = await refreshedUser.getIdToken(true);
      
      console.log("Calling SaaS backend firebase-sync...");
      await authApi.firebaseSync(idToken);
      
      console.log("Sync complete. Refreshing user data...");
      await loadUser();
      return true;
    } catch (err: any) {
      console.error("Firebase sync failed:", err.message);
      return false;
    }
  }, [loadUser]);

  const handleGoogleCallback = useCallback(async (code: string) => {
    setState((s) => ({ ...s, isLoading: true, error: null }));
    try {
      const referralCode = localStorage.getItem("referral_code") || undefined;
      const response = await authApi.googleCallback(code, referralCode);

      if ('requires_2fa' in response && response.requires_2fa) {
        setState((s) => ({
          ...s,
          isLoading: false,
          is2FAPending: true,
          challengeToken: response.challenge_token,
        }));
        return { requires_2fa: true };
      }

      if ('user' in response && 'token' in response) {
        const { user, token } = response;
        tokenStorage.set(token);
        localStorage.removeItem("referral_code");
        setState((s) => ({ ...s, user, isLoading: false, error: null, is2FAPending: false, challengeToken: null }));
        return user;
      }

      throw new Error("Invalid response from server");
    } catch (err: any) {
      const message = err?.message ?? "Google authentication failed.";
      setState((s) => ({ ...s, isLoading: false, error: message }));
      throw err;
    }
  }, []);

  const logout = useCallback(async () => {
    try {
      await authApi.logout();
      await firebaseSignOut(firebaseAuth).catch(() => {});
    } finally {
      tokenStorage.clear();
      setState({ user: null, isLoading: false, error: null, is2FAPending: false, challengeToken: null, firebaseUser: null });
    }
  }, []);

  const clearError = useCallback(() => {
    setState((s) => ({ ...s, error: null }));
  }, []);

  return (
    <AuthContext.Provider
      value={{
        ...state,
        login,
        verify2fa,
        signup,
        handleGoogleCallback,
        logout,
        clearError,
        refreshUser: loadUser,
        syncFirebaseVerification,
        isAuthenticated: !!state.user,
        is2FAPending: state.is2FAPending,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error("useAuth must be used within AuthProvider");
  return ctx;
}
