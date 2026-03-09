import { Outlet, Link } from "react-router-dom";
import { useTheme } from "@/contexts/ThemeContext";
import { useI18n } from "@/contexts/I18nContext";
import { Sun, Moon, Menu, X, Mail, MapPin } from "lucide-react";
import { useState } from "react";
import { LanguageSwitcher } from "@/components/LanguageSwitcher";

const NAV_LINK_KEYS = [
  { key: "pub.products", to: "/residential-proxies" },
  { key: "pub.locations", to: "/locations" },
  { key: "pub.pricing", to: "/pricing" },
  { key: "pub.docs", to: "/docs" },
  { key: "pub.useCases", to: "/use-cases" },
  { key: "pub.blog", to: "/blog" },
];

const FOOTER_PRODUCTS = [
  { label: "Residential Proxies", to: "/residential-proxies" },
  { label: "Premium Residential", to: "/residential-proxies" },
  { label: "Datacenter Proxies", to: "/datacenter-proxies" },
  { label: "Mobile Proxies", to: "/mobile-proxies" },
  { label: "ISP Proxies", to: "/isp-proxies" },
  { label: "SOCKS5 Proxies", to: "/socks5-proxies" },
];

const FOOTER_EXPLORE_KEYS = [
  { label: "About Us", to: "/ethics" },
  { label: "Ethics", to: "/ethics" },
  { label: "Blog", to: "/blog" },
  { label: "Docs", to: "/docs" },
  { label: "Locations", to: "/locations" },
  { label: "Pricing", to: "/pricing" },
];

const FOOTER_USE_CASES = [
  { label: "Market Research", to: "/use-cases" },
  { label: "Ad Verification", to: "/use-cases" },
  { label: "SEO Monitoring", to: "/use-cases" },
  { label: "Price Intelligence", to: "/use-cases" },
  { label: "Brand Protection", to: "/use-cases" },
  { label: "All Use Cases", to: "/use-cases" },
];

const FOOTER_LEGAL = [
  { label: "Terms of Service", to: "/terms" },
  { label: "Privacy Policy", to: "/privacy" },
  { label: "Cookie Policy", to: "/cookies" },
  { label: "Refund Policy", to: "/terms" },
  { label: "Service Level Agreement", to: "/terms" },
];

const CERTIFICATIONS = ["ISO 27001", "ISO 27018", "SOC 2", "GDPR"];

export function PublicLayout() {
  const { theme, toggleTheme } = useTheme();
  const { t } = useI18n();
  const [mobileOpen, setMobileOpen] = useState(false);

  return (
    <div className="flex min-h-screen flex-col">
      <header className="sticky top-0 z-40 border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
        <div className="container flex h-12 items-center justify-between">
          <Link to="/" className="flex items-center gap-2 text-base font-bold tracking-tight text-primary">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" className="text-primary">
              <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
            </svg>
            UpgradedProxy
          </Link>

          <nav className="hidden items-center gap-5 md:flex">
            {NAV_LINK_KEYS.map((link) => (
              <Link key={link.to} to={link.to} className="text-sm text-muted-foreground transition-colors hover:text-foreground">
                {t(link.key)}
              </Link>
            ))}
            <LanguageSwitcher compact />
            <button onClick={toggleTheme} className="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground" aria-label="Toggle theme">
              {theme === "dark" ? <Sun className="h-4 w-4" /> : <Moon className="h-4 w-4" />}
            </button>
            <Link to="/login" className="text-sm font-medium text-primary hover:underline">{t("pub.login")}</Link>
          </nav>

          <div className="flex items-center gap-2 md:hidden">
            <LanguageSwitcher compact />
            <button onClick={toggleTheme} className="rounded-md p-1.5 text-muted-foreground hover:bg-muted" aria-label="Toggle theme">
              {theme === "dark" ? <Sun className="h-4 w-4" /> : <Moon className="h-4 w-4" />}
            </button>
            <button onClick={() => setMobileOpen(!mobileOpen)} className="rounded-md p-1.5 text-muted-foreground hover:bg-muted" aria-label="Menu">
              {mobileOpen ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
            </button>
          </div>
        </div>

        {mobileOpen && (
          <nav className="border-t bg-background px-4 py-3 md:hidden">
            {NAV_LINK_KEYS.map((link) => (
              <Link key={link.to} to={link.to} onClick={() => setMobileOpen(false)} className="block py-2 text-sm text-muted-foreground hover:text-foreground">{t(link.key)}</Link>
            ))}
            <Link to="/login" onClick={() => setMobileOpen(false)} className="block py-2 text-sm font-medium text-primary">{t("pub.login")}</Link>
          </nav>
        )}
      </header>

      <main className="flex-1">
        <Outlet />
      </main>

      {/* Professional dark footer */}
      <footer className="bg-[hsl(var(--footer-bg))] text-[hsl(var(--footer-foreground))]">
        {/* Certifications bar */}
        <div className="border-b border-white/10 py-6">
          <div className="container">
            <p className="text-xs uppercase tracking-wider text-[hsl(var(--footer-muted))] mb-3">{t("cert.noCornerscut")}</p>
            <div className="flex flex-wrap gap-3">
              {CERTIFICATIONS.map((c) => (
                <span key={c} className="rounded-full border border-white/15 px-3 py-1 text-xs font-medium text-[hsl(var(--footer-foreground))]">{c}</span>
              ))}
            </div>
          </div>
        </div>

        {/* Main footer grid */}
        <div className="container py-10">
          <div className="grid gap-8 sm:grid-cols-2 lg:grid-cols-5">
            {/* Brand + Contact */}
            <div className="lg:col-span-1">
              <p className="text-base font-bold text-white">UpgradedProxy</p>
              <div className="mt-4 space-y-2 text-xs text-[hsl(var(--footer-muted))]">
                <p className="flex items-start gap-2">
                  <MapPin className="h-3.5 w-3.5 mt-0.5 shrink-0" />
                  <span>Premium Proxy Services<br />Global Infrastructure</span>
                </p>
                <p className="flex items-center gap-2">
                  <Mail className="h-3.5 w-3.5 shrink-0" />
                  support@upgradedproxy.com
                </p>
              </div>
              <div className="mt-3 flex items-center gap-1.5">
                <span className="h-2 w-2 rounded-full bg-green-500" />
                <span className="text-xs font-medium text-green-400">{t("footer.servicesOnline")}</span>
              </div>
            </div>

            {/* Explore */}
            <div>
              <p className="text-sm font-semibold text-white border-l-2 border-primary pl-2">{t("footer.explore")}</p>
              <ul className="mt-3 space-y-1.5">
                {FOOTER_EXPLORE_KEYS.map((l) => (
                  <li key={l.label}><Link to={l.to} className="text-xs text-[hsl(var(--footer-muted))] hover:text-white transition-colors">{l.label}</Link></li>
                ))}
              </ul>
            </div>

            {/* Products */}
            <div>
              <p className="text-sm font-semibold text-white border-l-2 border-primary pl-2">{t("footer.products")}</p>
              <ul className="mt-3 space-y-1.5">
                {FOOTER_PRODUCTS.map((l) => (
                  <li key={l.label}><Link to={l.to} className="text-xs text-[hsl(var(--footer-muted))] hover:text-white transition-colors">{l.label}</Link></li>
                ))}
              </ul>
            </div>

            {/* Use Cases */}
            <div>
              <p className="text-sm font-semibold text-white border-l-2 border-primary pl-2">{t("footer.useCases")}</p>
              <ul className="mt-3 space-y-1.5">
                {FOOTER_USE_CASES.map((l) => (
                  <li key={l.label}><Link to={l.to} className="text-xs text-[hsl(var(--footer-muted))] hover:text-white transition-colors">{l.label}</Link></li>
                ))}
              </ul>
            </div>

            {/* Legal */}
            <div>
              <p className="text-sm font-semibold text-white border-l-2 border-primary pl-2">{t("footer.legal")}</p>
              <ul className="mt-3 space-y-1.5">
                {FOOTER_LEGAL.map((l) => (
                  <li key={l.label}><Link to={l.to} className="text-xs text-[hsl(var(--footer-muted))] hover:text-white transition-colors">{l.label}</Link></li>
                ))}
              </ul>
            </div>
          </div>
        </div>

        {/* Bottom bar */}
        <div className="border-t border-white/10">
          <div className="container flex flex-col items-center justify-between gap-2 py-4 sm:flex-row">
            <p className="text-xs text-[hsl(var(--footer-muted))]">© {new Date().getFullYear()} UpgradedProxy. {t("footer.allRights")}</p>
            <div className="flex items-center gap-3">
              {["Visa", "MC", "PayPal", "Crypto"].map((m) => (
                <span key={m} className="rounded border border-white/15 px-2 py-0.5 text-[10px] text-[hsl(var(--footer-muted))]">{m}</span>
              ))}
            </div>
          </div>
        </div>
      </footer>
    </div>
  );
}
