import { Suspense, lazy, useEffect } from "react";
import { Toaster } from "@/components/ui/toaster";
import { Toaster as Sonner } from "@/components/ui/sonner";
import { TooltipProvider } from "@/components/ui/tooltip";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { BrowserRouter, Routes, Route } from "react-router-dom";
import { HelmetProvider } from "react-helmet-async";
import { AuthProvider } from "@/contexts/AuthContext";
import { I18nProvider } from "@/contexts/I18nContext";
import { ThemeProvider } from "@/contexts/ThemeContext";
import { PaymentConfigProvider } from "@/contexts/PaymentConfigContext";
import { CurrencyProvider } from "@/contexts/CurrencyContext";
import { RequireAuth } from "@/components/guards/RequireAuth";
import { RequireRole } from "@/components/guards/RequireRole";
import { PublicLayout } from "@/components/layout/PublicLayout";
import { AppShell } from "@/components/layout/AppShell";
import { ErrorBoundary } from "@/components/shared/ErrorBoundary";
import NotFound from "./pages/NotFound";

// Public pages
const Landing = lazy(() => import("./pages/Landing"));
const Pricing = lazy(() => import("./pages/Pricing"));
const Docs = lazy(() => import("./pages/Docs"));
const Blog = lazy(() => import("./pages/Blog"));
const BlogPost = lazy(() => import("./pages/BlogPost"));

// Money pages
const ResidentialProxies = lazy(() => import("./pages/ResidentialProxies"));
const DatacenterProxies = lazy(() => import("./pages/DatacenterProxies"));
const ISPProxies = lazy(() => import("./pages/ISPProxies"));
const MobileProxies = lazy(() => import("./pages/MobileProxies"));
const Socks5Proxies = lazy(() => import("./pages/Socks5Proxies"));
const ProxyAPI = lazy(() => import("./pages/ProxyAPI"));
const Locations = lazy(() => import("./pages/Locations"));
const UseCases = lazy(() => import("./pages/UseCases"));

// Legal pages
const Privacy = lazy(() => import("./pages/Privacy"));
const Terms = lazy(() => import("./pages/Terms"));
const CookiePolicy = lazy(() => import("./pages/CookiePolicy"));
const Ethics = lazy(() => import("./pages/Ethics"));
const Status = lazy(() => import("./pages/Status"));

// Auth pages
const Login = lazy(() => import("./pages/Login"));
const Signup = lazy(() => import("./pages/Signup"));
const ForgotPassword = lazy(() => import("./pages/ForgotPassword"));
const ResetPassword = lazy(() => import("./pages/ResetPassword"));
const GoogleCallback = lazy(() => import("./pages/GoogleCallback"));

// Docs sub-pages
const ScrapingBrowserDocs = lazy(() => import("./pages/docs/ScrapingBrowserDocs"));

// Client dashboard
const AppDashboard = lazy(() => import("./pages/app/Dashboard"));
const Proxies = lazy(() => import("./pages/app/Proxies"));
const ProxyList = lazy(() => import("./pages/app/ProxyList"));
const Usage = lazy(() => import("./pages/app/Usage"));
const AppSettings = lazy(() => import("./pages/app/Settings"));
const Billing = lazy(() => import("./pages/app/Billing"));
const Invoices = lazy(() => import("./pages/app/Invoices"));
const Referral = lazy(() => import("./pages/app/Referral"));
const FAQ = lazy(() => import("./pages/app/FAQ"));
const ScraperAPI = lazy(() => import("./pages/app/ScraperAPI"));
const ScrapingBrowser = lazy(() => import("./pages/app/ScrapingBrowser"));
const Support = lazy(() => import("./pages/app/Support"));
const RateLimits = lazy(() => import("./pages/app/RateLimits"));
const Security = lazy(() => import("./pages/app/Security"));
const AppOrganization = lazy(() => import("./pages/app/Organization"));
const CustomDomains = lazy(() => import("./pages/app/CustomDomains"));
const Install = lazy(() => import("./pages/app/Install"));

// Admin dashboard
const AdminDashboard = lazy(() => import("./pages/admin/Dashboard"));
const AdminUsers = lazy(() => import("./pages/admin/Users"));
const AdminProducts = lazy(() => import("./pages/admin/Products"));
const AdminAudit = lazy(() => import("./pages/admin/Audit"));
const AdminBlog = lazy(() => import("./pages/admin/Blog"));
const AdminAlerts = lazy(() => import("./pages/admin/Alerts"));
const AdminPaymentGateways = lazy(() => import("./pages/admin/PaymentGateways"));
const AdminTopUpSettings = lazy(() => import("./pages/admin/TopUpSettings"));
const AdminSettings = lazy(() => import("./pages/admin/AdminSettings"));
const AdminInvoices = lazy(() => import("./pages/admin/AdminInvoices"));
const AdminSupport = lazy(() => import("./pages/admin/Support"));
const AdminCoupons = lazy(() => import("./pages/admin/Coupons"));
const AdminPermissions = lazy(() => import("./pages/admin/Permissions"));
const AdminResellers = lazy(() => import("./pages/admin/Resellers"));
const AdminFraudDetection = lazy(() => import("./pages/admin/FraudDetection"));
const AdminSLAMonitoring = lazy(() => import("./pages/admin/SLAMonitoring"));
const AdminCurrencies = lazy(() => import("./pages/admin/Currencies"));
const AdminManualPayments = lazy(() => import("./pages/admin/ManualPayments"));
const AdminReferrals = lazy(() => import("./pages/admin/Referrals"));
const AdminFulfillmentLogs = lazy(() => import("./pages/admin/FulfillmentLogs"));
const AdminEmailTemplates = lazy(() => import("./pages/admin/AdminEmailTemplates"));
const AdminUserDetail = lazy(() => import("./pages/admin/UserDetail"));

const queryClient = new QueryClient();

function Loading() {
  return <div className="flex min-h-screen items-center justify-center text-muted-foreground">Loading…</div>;
}

function App() {
  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    const ref = params.get("ref");
    if (ref) {
      localStorage.setItem("referral_code", ref);
      console.log("Captured referral code:", ref);
    }
  }, []);

  return (
    <HelmetProvider>
      <QueryClientProvider client={queryClient}>
        <ThemeProvider defaultTheme="dark" storageKey="vite-ui-theme">
          <I18nProvider>
            <CurrencyProvider>
              <PaymentConfigProvider>
                <AuthProvider>
                  <TooltipProvider>
                    <Toaster />
                    <Sonner />
                    <BrowserRouter>
                      <Suspense fallback={<Loading />}>
                        <ErrorBoundary>
                          <Routes>
                            {/* Public marketing pages */}
                            <Route element={<PublicLayout />}>
                              <Route path="/" element={<Landing />} />
                              <Route path="/pricing" element={<Pricing />} />
                              <Route path="/docs" element={<Docs />} />
                              <Route path="/docs/scraping-browser" element={<ScrapingBrowserDocs />} />
                              <Route path="/blog" element={<Blog />} />
                              <Route path="/blog/:slug" element={<BlogPost />} />
                              <Route path="/residential-proxies" element={<ResidentialProxies />} />
                              <Route path="/datacenter-proxies" element={<DatacenterProxies />} />
                              <Route path="/isp-proxies" element={<ISPProxies />} />
                              <Route path="/mobile-proxies" element={<MobileProxies />} />
                              <Route path="/socks5-proxies" element={<Socks5Proxies />} />
                              <Route path="/proxy-api" element={<ProxyAPI />} />
                              <Route path="/locations" element={<Locations />} />
                              <Route path="/use-cases" element={<UseCases />} />
                              <Route path="/privacy" element={<Privacy />} />
                              <Route path="/terms" element={<Terms />} />
                              <Route path="/cookies" element={<CookiePolicy />} />
                              <Route path="/ethics" element={<Ethics />} />
                              <Route path="/status" element={<Status />} />
                            </Route>

                            {/* Auth pages */}
                            <Route path="/login" element={<Login />} />
                            <Route path="/signup" element={<Signup />} />
                            <Route path="/forgot-password" element={<ForgotPassword />} />
                            <Route path="/reset-password" element={<ResetPassword />} />
                            <Route path="/auth/google/callback" element={<GoogleCallback />} />
                            <Route path="/install" element={<Install />} />

                            {/* Client dashboard */}
                            <Route
                              path="/app"
                              element={
                                <RequireAuth>
                                  <RequireRole role="client">
                                    <AppShell />
                                  </RequireRole>
                                </RequireAuth>
                              }
                            >
                              <Route index element={<AppDashboard />} />
                              <Route path="proxies/generate" element={<Proxies />} />
                              <Route path="proxies/:type" element={<ProxyList />} />
                              <Route path="proxies" element={<Proxies />} />
                              <Route path="usage" element={<Usage />} />
                              <Route path="settings" element={<AppSettings />} />
                              <Route path="billing" element={<Billing />} />
                              <Route path="invoices" element={<Invoices />} />
                              <Route path="referral" element={<Referral />} />
                              <Route path="faq" element={<FAQ />} />
                              <Route path="scraper-api" element={<ScraperAPI />} />
                              <Route path="scraping-browser" element={<ScrapingBrowser />} />
                              <Route path="support" element={<Support />} />
                              <Route path="rate-limits" element={<RateLimits />} />
                              <Route path="security" element={<Security />} />
                              <Route path="organization" element={<AppOrganization />} />
                              <Route path="custom-domains" element={<CustomDomains />} />
                            </Route>

                            {/* Admin dashboard */}
                            <Route
                              path="/admin"
                              element={
                                <RequireAuth>
                                  <RequireRole role="admin">
                                    <AppShell />
                                  </RequireRole>
                                </RequireAuth>
                              }
                            >
                              <Route index element={<AdminDashboard />} />
                              <Route path="users" element={<AdminUsers />} />
                              <Route path="users/:id" element={<AdminUserDetail />} />
                              <Route path="products" element={<AdminProducts />} />
                              <Route path="audit" element={<AdminAudit />} />
                              <Route path="blog" element={<AdminBlog />} />
                              <Route path="alerts" element={<AdminAlerts />} />
                              <Route path="payment-gateways" element={<AdminPaymentGateways />} />
                              <Route path="topup-settings" element={<AdminTopUpSettings />} />
                              <Route path="settings" element={<AdminSettings />} />
                              <Route path="invoices" element={<AdminInvoices />} />
                              <Route path="support" element={<AdminSupport />} />
                              <Route path="coupons" element={<AdminCoupons />} />
                              <Route path="permissions" element={<AdminPermissions />} />
                              <Route path="resellers" element={<AdminResellers />} />
                              <Route path="fraud-detection" element={<AdminFraudDetection />} />
                              <Route path="sla-monitoring" element={<AdminSLAMonitoring />} />
                              <Route path="currencies" element={<AdminCurrencies />} />
                              <Route path="manual-payments" element={<AdminManualPayments />} />
                              <Route path="referrals" element={<AdminReferrals />} />
                              <Route path="fulfillment-logs" element={<AdminFulfillmentLogs />} />
                              <Route path="email-templates" element={<AdminEmailTemplates />} />
                              <Route path="security" element={<Security />} />
                            </Route>

                            <Route path="*" element={<NotFound />} />
                          </Routes>
                        </ErrorBoundary>
                      </Suspense>
                    </BrowserRouter>
                  </TooltipProvider>
                </AuthProvider>
              </PaymentConfigProvider>
            </CurrencyProvider>
          </I18nProvider>
        </ThemeProvider>
      </QueryClientProvider>
    </HelmetProvider>
  );
}

export default App;
