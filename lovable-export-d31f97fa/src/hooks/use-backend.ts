import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { api, MessageSchema } from "@/lib/api/client";
import { clientApi } from "@/lib/api/dashboard";
import {
  UsageResponseSchema,
  EventSchema,
  AllowlistEntrySchema,
  ApiKeySchema,
  ApiKeyCreateResponseSchema,
  InvoiceSchema,
  PlanSchema,
  ProxyGenerateResponseSchema,
  SubscriptionSchema
} from "@/lib/api/dashboard";
import { z } from "zod";
import { useAuth } from "@/contexts/AuthContext";
import { twoFactorApi } from "@/lib/api/dashboard";

// ── Client Hooks ──────────────────────────────

export function useProfile() {
  return useQuery({
    queryKey: ["profile"],
    queryFn: async () => {
      return api.get("/auth/me", z.any()); // Or UserSchema if defined
    },
  });
}

export function useProfileInfo() {
  return useQuery({
    queryKey: ["profile-info"],
    queryFn: async () => {
      return api.get("/profile", z.any());
    },
  });
}

export function useUpdateProfile() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (params: { name?: string; password?: string; password_confirmation?: string }) => {
      return api.post("/profile", MessageSchema, params);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["profile"] });
      queryClient.invalidateQueries({ queryKey: ["profile-info"] });
    },
  });
}

export function useStats() {
  return useQuery({
    queryKey: ["stats"],
    queryFn: async () => {
      return api.get("/stats", z.any());
    },
    refetchInterval: 1000 * 60 * 5, // 5 min auto refetch
  });
}
export function useUsage(range: string = "7d") {
  return useQuery({
    queryKey: ["usage", range],
    queryFn: async () => {
      return api.get(`/me/usage?range=${encodeURIComponent(range)}`, UsageResponseSchema);
    },
    staleTime: 1000 * 60 * 5,
  });
}

export function useEvents() {
  return useQuery({
    queryKey: ["events"],
    queryFn: async () => {
      return api.get("/me/events", z.array(EventSchema));
    },
  });
}

export function useOrders(type?: string | null) {
  return useQuery({
    queryKey: ["orders", type],
    queryFn: async () => {
      // In Laravel, we use /proxies with optional type param
      const path = type ? `/proxies?type=${type}` : "/proxies";
      return api.get(path, z.array(z.any()));
    },
  });
}

export function useInvoices() {
  return useQuery({
    queryKey: ["invoices"],
    queryFn: async () => {
      return api.get("/invoices", z.array(InvoiceSchema));
    },
  });
}

export function useApiKeys() {
  const queryClient = useQueryClient();

  const query = useQuery({
    queryKey: ["api_keys"],
    queryFn: async () => {
      return api.get("/api_keys", z.array(ApiKeySchema));
    },
  });

  const createKey = useMutation({
    mutationFn: async (keyName: string) => {
      return api.post("/api_keys", ApiKeyCreateResponseSchema, { key_name: keyName });
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["api_keys"] }),
  });

  const deleteKey = useMutation({
    mutationFn: async (keyId: string) => {
      await api.delete(`/api_keys/${keyId}`, MessageSchema);
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["api_keys"] }),
  });

  return { ...query, createKey, revokeKey: deleteKey, deleteKey };
}

export function useSupportTickets() {
  const queryClient = useQueryClient();

  const query = useQuery({
    queryKey: ["tickets"],
    queryFn: async () => {
      const tickets = await api.get("/support/tickets", z.array(z.any()));
      return tickets.map((t: any) => ({
        ...t,
        messages: t.messages?.map((m: any) => ({
          ...m,
          sender: m.is_admin_reply ? "support" : "client",
          text: m.message,
          time: m.created_at,
          attachment_url: m.attachment_url,
          attachment_name: m.attachment_name,
        })) || []
      }));
    },
  });

  const createTicket = useMutation({
    mutationFn: async ({ subject, message, priority, attachment }: { subject: string; message: string; priority: string; attachment?: File }) => {
      const formData = new FormData();
      formData.append("subject", subject);
      formData.append("message", message);
      formData.append("priority", priority);
      if (attachment) formData.append("attachment", attachment);
      return api.post("/support/tickets", z.any(), formData);
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["tickets"] }),
  });

  const replyTicket = useMutation({
    mutationFn: async ({ id, message, attachment }: { id: number | string; message: string; attachment?: File }) => {
      const formData = new FormData();
      formData.append("message", message);
      if (attachment) formData.append("attachment", attachment);
      return api.post(`/support/tickets/${id}/reply`, z.any(), formData);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["tickets"] });
      queryClient.invalidateQueries({ queryKey: ["admin", "tickets"] });
    },
  });

  const updateStatus = useMutation({
    mutationFn: async ({ id, status }: { id: number | string; status: string }) => {
      return api.post(`/admin/support/tickets/${id}/status`, z.any(), { status });
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["tickets"] }),
  });

  return { ...query, createTicket, replyTicket, updateStatus };
}

export function useProducts() {
  return useQuery({
    queryKey: ["products"],
    queryFn: async () => {
      return api.get(`/products?_t=${Date.now()}`, z.array(PlanSchema));
    },
  });
}

// ── Proxy Actions ────────────────────────────

export function useGenerateProxy() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (params: { product_id: number; quantity: number }) => {
      return api.post("/proxies/generate", ProxyGenerateResponseSchema, params);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["orders"] });
      queryClient.invalidateQueries({ queryKey: ["profile"] });
      queryClient.invalidateQueries({ queryKey: ["subuser-status"] });
    },
  });
}

export function useIspStock() {
  return useQuery({
    queryKey: ["isp-stock"],
    queryFn: async () => {
      return api.get("/proxies/isp-stock", z.any());
    },
    staleTime: 1000 * 60 * 10,
  });
}

export function useOrderIsp() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (params: {
      product_id: number;
      quantity: number;
      country_code: string;
      city: string;
      isp: string;
      months: number;
      high_concurrency?: boolean;
    }) => {
      return api.post("/proxies/isp-order", z.any(), params);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["orders"] });
      queryClient.invalidateQueries({ queryKey: ["profile"] });
      queryClient.invalidateQueries({ queryKey: ["stats"] });
    },
  });
}

// ── Subuser Hooks ─────────────────────────────

export function useSubuserStatus() {
  return useQuery({
    queryKey: ["subuser-status"],
    queryFn: async () => {
      return api.get("/subusers/status", z.any());
    },
    staleTime: 1000 * 60 * 5,
  });
}

export function useSetupSubuser() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async () => {
      return api.post("/subusers/setup", MessageSchema);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["profile"] });
      queryClient.invalidateQueries({ queryKey: ["subuser-status"] });
    },
  });
}

export function useProxySettings() {
  return useQuery({
    queryKey: ["proxy-settings"],
    queryFn: async () => {
      return clientApi.getProxySettings();
    },
    staleTime: 1000 * 60 * 60, // 1 hour cache
  });
}

export function useAllowlist() {
  const queryClient = useQueryClient();

  const query = useQuery({
    queryKey: ["allowlist"],
    queryFn: async () => {
      return api.get("/allowlist", z.array(AllowlistEntrySchema));
    },
  });

  const addEntry = useMutation({
    mutationFn: async (entry: { ip: string; label?: string }) => {
      return api.post("/allowlist", AllowlistEntrySchema, entry);
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["allowlist"] }),
  });

  const removeEntry = useMutation({
    mutationFn: async (entryId: string | number) => {
      await api.post(`/allowlist/${entryId}`, MessageSchema, { _method: 'DELETE' });
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["allowlist"] }),
  });

  return { ...query, addEntry, removeEntry };
}

export function useReferrals() {
  return useQuery({
    queryKey: ["referrals"],
    queryFn: async () => {
      return clientApi.getReferralStats();
    },
  });
}

// ── Payment Actions ──────────────────────────

export function useStripeCheckout() {
  return useMutation({
    mutationFn: async (params: { amount: number }) => {
      const data: any = await api.post("/billing/checkout", z.object({ url: z.string() }), params);
      if (data.url) window.location.href = data.url;
      return data;
    },
  });
}

// ── Admin Hooks ──────────────────────────────

export function useAdminUsers() {
  return useQuery({
    queryKey: ["admin", "users"],
    queryFn: async () => {
      return api.get("/admin/users", z.array(z.any()));
    },
  });
}

export function useAdminStats() {
  return useQuery({
    queryKey: ["admin", "stats"],
    queryFn: async () => {
      return api.get("/admin/stats", z.any());
    },
    refetchInterval: 1000 * 60,
  });
}

export function useAdminTickets() {
  const queryClient = useQueryClient();

  const query = useQuery({
    queryKey: ["admin", "tickets"],
    queryFn: async () => {
      const data = await api.get("/admin/support/tickets", z.array(z.any()));
      return data.map((t: any) => ({
        id: String(t.id),
        user: t.user?.email || "Unknown",
        subject: t.subject,
        status: t.status,
        priority: t.priority === "normal" ? "medium" : t.priority,
        created_at: t.created_at,
        updated_at: t.updated_at,
        messages: t.messages?.map((m: any) => ({
          sender: m.is_admin_reply ? "support" : "client",
          text: m.message,
          time: m.created_at,
          attachment_url: m.attachment_url,
          attachment_name: m.attachment_name,
        })) || [],
      }));
    },
  });

  const replyTicket = useMutation({
    mutationFn: async ({ id, message, attachment }: { id: number | string; message: string; attachment?: File }) => {
      const formData = new FormData();
      formData.append("message", message);
      if (attachment) formData.append("attachment", attachment);
      return api.post(`/admin/support/tickets/${id}/reply`, MessageSchema, formData);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["admin", "tickets"] });
      queryClient.invalidateQueries({ queryKey: ["tickets"] });
    },
  });

  const updateStatus = useMutation({
    mutationFn: async ({ id, status }: { id: number | string; status: string }) => {
      return api.post(`/admin/support/tickets/${id}/status`, MessageSchema, { status });
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["admin", "tickets"] }),
  });

  const deleteTicket = useMutation({
    mutationFn: async (id: number | string) => {
      await api.delete(`/admin/support/tickets/${id}`, MessageSchema);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["admin", "tickets"] });
      queryClient.invalidateQueries({ queryKey: ["tickets"] });
    },
  });

  return { ...query, replyTicket, updateStatus, deleteTicket };
}

export function useAdminPaymentGateways(refresh?: boolean) {
  return useQuery({
    queryKey: ["admin", "payment-gateways", refresh],
    queryFn: async () => {
      const url = refresh ? "/admin/payment-gateways?refresh=true" : "/admin/payment-gateways";
      return api.get(url, z.any());
    },
  });
}

// Blog Hooks
export function useBlogPosts() {
  return useQuery({
    queryKey: ["blog", "posts"],
    queryFn: async () => {
      return api.get("/blog", z.array(z.any()));
    },
  });
}

export function useBlogPost(slug: string) {
  return useQuery({
    queryKey: ["blog", "post", slug],
    queryFn: async () => {
      return api.get(`/blog/${slug}`, z.any());
    },
    enabled: !!slug,
  });
}

export function useAdminBlogPosts() {
  return useQuery({
    queryKey: ["admin", "blog"],
    queryFn: async () => {
      return api.get("/admin/blog", z.array(z.any()));
    },
  });
}

export function useCreateBlogPost() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (data: any) => api.post("/admin/blog", z.any(), data),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["admin", "blog"] }),
  });
}

export function useUpdateBlogPost() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, data }: { id: string | number; data: any }) =>
      api.put(`/admin/blog/${id}`, z.any(), data),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["admin", "blog"] }),
  });
}

export function useDeleteBlogPost() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: string | number) => api.delete(`/admin/blog/${id}`, z.any()),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["admin", "blog"] }),
  });
}

export function usePublishBlogPost() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, is_draft }: { id: string | number; is_draft?: boolean }) =>
      api.post(`/admin/blog/${id}/publish`, z.any(), { is_draft }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["admin", "blog"] });
      queryClient.invalidateQueries({ queryKey: ["blog", "posts"] });
    },
  });
}

// Auto-Blog Hooks
export function useAutoBlogStatus() {
  return useQuery({
    queryKey: ["admin", "blog", "automation"],
    queryFn: () => api.get("/admin/blog/automation", z.any()),
  });
}

/**
 * Update Auto-Blog settings (including Gemini and Telegram)
 */
export function useUpdateAutoBlogSettings() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (data: any) => api.post("/admin/blog/automation/settings", z.any(), data),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["admin", "blog", "automation"] }),
  });
}

export function useAddAutoBlogKeyword() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (data: { keyword: string; category?: string }) =>
      api.post("/admin/blog/automation/keywords", z.any(), data),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["admin", "blog", "automation"] }),
  });
}

export function useDeleteAutoBlogKeyword() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: number | string) =>
      api.delete(`/admin/blog/automation/keywords/${id}`, z.any()),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["admin", "blog", "automation"] }),
  });
}

export function useTriggerAutoBlog() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (params?: { keyword_id?: number }) =>
      api.post("/admin/blog/automation/trigger", z.any(), params || {}),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["admin", "blog"] });
      queryClient.invalidateQueries({ queryKey: ["blog", "posts"] });
      queryClient.invalidateQueries({ queryKey: ["admin", "blog", "automation"] });
    },
  });
}

export function useTestTelegram() {
  return useMutation({
    mutationFn: () => api.post("/admin/blog/automation/telegram-test", z.any()),
  });
}

export function useTestGoogleIndexing() {
  return useMutation({
    mutationFn: () => api.post("/admin/blog/automation/google-test", z.any()),
  });
}

export function useSubmitUrlToIndex() {
  return useMutation({
    mutationFn: (postId: number | string) =>
      api.post("/admin/blog/automation/google-index-post", z.any(), { post_id: postId }),
  });
}

export function useSharePostToTelegram() {
  return useMutation({
    mutationFn: (postId: number | string) =>
      api.post("/admin/blog/automation/telegram-share-post", z.any(), { post_id: postId }),
  });
}

export function useTestFacebookSharing() {
  return useMutation({
    mutationFn: () => api.post("/admin/blog/automation/facebook-test", z.any()),
  });
}

export function useSharePostToFacebook() {
  return useMutation({
    mutationFn: (postId: number | string) =>
      api.post("/admin/blog/automation/facebook-share-post", z.any(), { post_id: postId }),
  });
}

export function useTestXSharing() {
  return useMutation({
    mutationFn: () => api.post("/admin/blog/automation/x-test", z.any()),
  });
}

export function useSharePostToX() {
  return useMutation({
    mutationFn: (postId: number | string) =>
      api.post("/admin/blog/automation/x-share-post", z.any(), { post_id: postId }),
  });
}

// Alert Hooks
export function useAdminAlertConfig() {
  return useQuery({
    queryKey: ["admin", "alerts", "config"],
    queryFn: async () => {
      return api.get("/admin/alerts/config", z.any());
    },
  });
}

export function useUpdateAlertConfig() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (data: any) => api.patch("/admin/alerts/config", z.any(), data),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["admin", "alerts", "config"] }),
  });
}

// Fraud Detection Hooks
export function useAdminFraudSignals(resolved?: boolean) {
  return useQuery({
    queryKey: ["admin", "fraud", "signals", resolved],
    queryFn: async () => {
      const url = resolved !== undefined ? `/admin/fraud/signals?resolved=${resolved}` : "/admin/fraud/signals";
      return api.get(url, z.array(z.any()));
    },
  });
}

export function useAdminRiskScores() {
  return useQuery({
    queryKey: ["admin", "fraud", "risk-scores"],
    queryFn: async () => {
      return api.get("/admin/fraud/risk-scores", z.array(z.any()));
    },
  });
}

export function useAdminLoginHistory() {
  return useQuery({
    queryKey: ["admin", "fraud", "login-history"],
    queryFn: async () => {
      return api.get("/admin/fraud/login-history", z.array(z.any()));
    },
  });
}

export function useResolveFraudSignal() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: number | string) => api.put(`/admin/fraud/signals/${id}/resolve`, z.any()),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["admin", "fraud", "signals"] });
      // Also invalidate dashboard stats since signals changed
      queryClient.invalidateQueries({ queryKey: ["admin-stats"] });
    },
  });
}

// ── Admin Referral Hooks ──────────────────────

export function useAdminReferralStats() {
  return useQuery({
    queryKey: ["admin", "referrals", "stats"],
    queryFn: async () => {
      return api.get("/admin/referrals/stats", z.any());
    },
  });
}

export function useAdminReferralEarnings(status?: string, page: number = 1) {
  return useQuery({
    queryKey: ["admin", "referrals", "earnings", status, page],
    queryFn: async () => {
      const params = new URLSearchParams();
      if (status) params.append("status", status);
      if (page > 1) params.append("page", page.toString());

      const queryString = params.toString();
      const url = queryString ? `/admin/referrals/earnings?${queryString}` : "/admin/referrals/earnings";
      return api.get(url, z.any());
    },
  });
}

export function useUpdateInfluencerRate() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (params: { user_id: number | string; custom_rate: number | null }) => {
      return api.post("/admin/referrals/influencer-rate", MessageSchema, params);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["admin", "users"] });
      queryClient.invalidateQueries({ queryKey: ["admin", "referrals", "stats"] });
    },
  });
}

// ── 2FA Hooks ────────────────────────────────

export function use2FASetup() {
  return useQuery({
    queryKey: ["auth", "2fa", "setup"],
    queryFn: () => twoFactorApi.setup(),
    staleTime: 0,
    retry: false,
  });
}

export function use2FAConfirm() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (code: string) => twoFactorApi.confirm(code),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["profile"] });
      queryClient.invalidateQueries({ queryKey: ["profile-info"] });
    },
  });
}

export function use2FADisable() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (data: { password: string; code?: string }) => twoFactorApi.disable(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["profile"] });
      queryClient.invalidateQueries({ queryKey: ["profile-info"] });
    },
  });
}

export function use2FARecoveryCodes(enabled: boolean = false) {
  return useQuery({
    queryKey: ["auth", "2fa", "recovery-codes"],
    queryFn: () => twoFactorApi.getRecoveryCodes(),
    enabled: enabled,
  });
}

export function useNotifications() {
  const queryClient = useQueryClient();
  const { isAuthenticated } = useAuth();

  const query = useQuery({
    queryKey: ["notifications"],
    queryFn: async () => {
      return api.get("/notifications", z.array(z.any()));
    },
    refetchInterval: 1000 * 60, // Poll every 1 min
    enabled: isAuthenticated,
  });

  const markRead = useMutation({
    mutationFn: async (id: string) => {
      return api.post(`/notifications/${id}/read`, MessageSchema);
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["notifications"] }),
  });

  const markAllRead = useMutation({
    mutationFn: async () => {
      return api.post("/notifications/mark-read", MessageSchema);
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["notifications"] }),
  });

  return { ...query, markRead, markAllRead };
}

export function useAdminUserDetail(id: string | number | null) {
  return useQuery({
    queryKey: ["admin", "users", id],
    queryFn: async () => {
      if (!id) return null;
      const user = await api.get(`/admin/users/${id}`, z.any());
      if (!user) throw new Error("User not found");
      return {
        ...user,
        full_name: user.name,
        user_id: user.id,
        is_banned: user.role === "banned",
      };
    },
    enabled: !!id,
  });
}

export function useAdminUserOrders(id: string | number | null) {
  return useQuery({
    queryKey: ["admin", "users", id, "orders"],
    queryFn: async () => {
      return api.get(`/admin/users/${id}/orders`, z.array(z.any()));
    },
    enabled: !!id,
  });
}

export function useAdminUserActions() {
  const queryClient = useQueryClient();

  const updatePassword = useMutation({
    mutationFn: async ({ id, data }: { id: string | number; data: any }) => {
      return api.post(`/admin/users/${id}/password`, MessageSchema, data);
    },
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ["admin", "users", variables.id] });
    },
  });

  const addOrder = useMutation({
    mutationFn: async ({ id, data }: { id: string | number; data: any }) => {
      return api.post(`/admin/users/${id}/orders`, MessageSchema, data);
    },
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ["admin", "users", variables.id, "orders"] });
      queryClient.invalidateQueries({ queryKey: ["admin-user-stats", String(variables.id)] });
    },
  });

  const deleteOrder = useMutation({
    mutationFn: async ({ orderId, userId }: { orderId: string | number; userId: string | number }) => {
      return api.delete(`/admin/orders/${orderId}`, MessageSchema);
    },
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ["admin", "users", variables.userId, "orders"] });
      queryClient.invalidateQueries({ queryKey: ["admin-user-stats", String(variables.userId)] });
    },
  });

  const deleteProxy = useMutation({
    mutationFn: async ({ proxyId, userId }: { proxyId: string | number; userId: string | number }) => {
      return api.delete(`/admin/proxies/${proxyId}`, MessageSchema);
    },
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ["admin", "users", variables.userId, "orders"] });
      queryClient.invalidateQueries({ queryKey: ["admin-user-stats", String(variables.userId)] });
    },
  });

  const updateRole = useMutation({
    mutationFn: async ({ id, role }: { id: string | number; role: string }) => {
      return api.post(`/admin/users/${id}/role`, MessageSchema, { role });
    },
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ["admin", "users", variables.id] });
      queryClient.invalidateQueries({ queryKey: ["admin-users"] });
    },
  });

  return { updatePassword, addOrder, deleteOrder, deleteProxy, updateRole };
}

export function useAdminAction() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (params: { action: string;[key: string]: any }) => {
      const { action, ...payload } = params;
      if (action === "list_users") return api.get("/admin/users", z.array(z.any()));
      if (action === "ban_user" || action === "unban_user") {
        return api.post("/admin/users/ban", MessageSchema, {
          user_id: payload.user_id,
          reason: payload.ban_reason || (action === "ban_user" ? "Banned by admin" : "Unbanned by admin"),
        });
      }
      if (action === "update_influencer_rate") {
        return api.post("/admin/referrals/influencer-rate", MessageSchema, {
          user_id: payload.user_id,
          custom_rate: payload.custom_rate,
        });
      }
      if (action === "adjust_balance") {
        return api.post("/admin/users/balance", z.object({ message: z.string(), new_balance: z.number() }), {
          user_id: payload.user_id,
          amount: payload.amount,
          reason: payload.reason || "Admin adjustment",
        });
      }
      if (action === "get_user_stats") {
        return api.get(`/admin/users/${payload.user_id}/stats`, z.any());
      }
      throw new Error("Action not supported in Laravel bridge");
    },
    onSuccess: (data, variables) => {
      queryClient.invalidateQueries({ queryKey: ["admin-users"] });
      if (variables.user_id) {
        queryClient.invalidateQueries({ queryKey: ["admin", "users", variables.user_id] });
      }
    },
  });
}
