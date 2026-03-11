import {
  createContext,
  useContext,
  useState,
  useCallback,
  useEffect,
  type ReactNode,
} from "react";
import { authApi, type User, type LoginInput, type SignupInput } from "@/lib/api/auth";
import { tokenStorage } from "@/lib/api/client";

interface AuthState {
  user: User | null;
  isLoading: boolean;
  error: string | null;
  is2FAPending: boolean;
  challengeToken: string | null;
}

interface AuthContextValue extends AuthState {
  login: (data: LoginInput) => Promise<any>;
  verify2fa: (code: string) => Promise<User>;
  signup: (data: SignupInput) => Promise<User>;
  handleGoogleCallback: (code: string) => Promise<any>;
  logout: () => Promise<void>;
  clearError: () => void;
  refreshUser: () => Promise<void>;
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
  });

  const loadUser = useCallback(async () => {
    const token = tokenStorage.get();
    if (!token) {
      setState({ user: null, isLoading: false, error: null, is2FAPending: false, challengeToken: null });
      return;
    }
    try {
      const user = await authApi.me();
      setState({ user, isLoading: false, error: null, is2FAPending: false, challengeToken: null });
    } catch {
      tokenStorage.clear();
      setState({ user: null, isLoading: false, error: null, is2FAPending: false, challengeToken: null });
    }
  }, []);

  useEffect(() => {
    loadUser();
  }, [loadUser]);

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

      const response = await authApi.signup(signupData);
      if ('requires_2fa' in response && response.requires_2fa) {
        throw new Error("Registration should not redirect to 2FA challenge");
      }

      if ('user' in response && 'token' in response) {
        const { user, token } = response;
        tokenStorage.set(token);
        setState({ user, isLoading: false, error: null, is2FAPending: false, challengeToken: null });

        // Clean up referral code after successful signup
        if (referralCode) {
          localStorage.removeItem("referral_code");
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
        // Clear referral after successful use
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
    } finally {
      tokenStorage.clear();
      setState({ user: null, isLoading: false, error: null, is2FAPending: false, challengeToken: null });
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
