import { z } from "zod";
import { api, MessageSchema } from "./client";

export const UserSchema = z.object({
  id: z.string(),
  email: z.string().email(),
  name: z.string(),
  role: z.enum(["client", "admin", "banned"]),
  balance: z.number().default(0),
  referral_code: z.string().nullable().optional(),
  avatar: z.string().nullable().optional(),
  is_2fa_enabled: z.boolean().default(false),
  email_verified_at: z.string().nullable().optional(),
  has_claimed_trial: z.boolean().default(false),
});

export type User = z.infer<typeof UserSchema>;
export type UserRole = User["role"];

const AuthResponseSchema = z.union([
  z.object({
    user: UserSchema,
    token: z.string(),
    requires_2fa: z.literal(false).optional(),
  }),
  z.object({
    requires_2fa: z.literal(true),
    challenge_token: z.string(),
  })
]);

export const loginSchema = z.object({
  email: z
    .string()
    .trim()
    .min(1, "Email is required")
    .email("Enter a valid email address"),
  password: z
    .string()
    .min(1, "Password is required"),
});

export const signupSchema = z.object({
  name: z
    .string()
    .trim()
    .min(1, "Name is required"),
  email: z
    .string()
    .trim()
    .min(1, "Email is required")
    .email("Enter a valid email address"),
  password: z
    .string()
    .min(8, "Password must be at least 8 characters"),
  password_confirmation: z.string(),
  referral_code: z.string().optional(),
}).refine((d) => d.password === d.password_confirmation, {
  message: "Passwords do not match",
  path: ["password_confirmation"],
});

export type LoginInput = z.infer<typeof loginSchema>;
export type SignupInput = z.infer<typeof signupSchema>;

export const authApi = {
  me: () => api.get("/auth/me", UserSchema),
  login: (data: LoginInput) => api.post("/auth/login", AuthResponseSchema, data),
  signup: (data: SignupInput) => api.post("/auth/signup", AuthResponseSchema, data),
  logout: () => api.post("/auth/logout", MessageSchema),
  verify2fa: (data: { challenge_token: string; code: string }) =>
    api.post("/auth/2fa/verify", AuthResponseSchema, data),
  googleRedirect: () => api.get("/auth/google/redirect", z.object({ url: z.string() })),
  googleCallback: (code: string, referralCode?: string) =>
    api.get(`/auth/google/callback?code=${code}${referralCode ? `&referral_code=${referralCode}` : ""}`, AuthResponseSchema),
  /**
   * Sync Firebase email verification to SaaS.
   * Called ONLY after Firebase confirms emailVerified = true.
   * Sends the Firebase ID token to backend for validation.
   */
  firebaseSync: (firebaseIdToken: string) =>
    api.post("/auth/firebase-sync", z.object({ message: z.string(), user: UserSchema.optional() }), {
      firebase_id_token: firebaseIdToken,
    }),
};

