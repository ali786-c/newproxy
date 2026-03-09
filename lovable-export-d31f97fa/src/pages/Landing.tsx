import { useState } from "react";
import { clientApi } from "@/lib/api/dashboard";
import { SEOHead } from "@/components/seo/SEOHead";
import { SchemaOrg, SchemaFAQ } from "@/components/seo/SchemaComponents";
import { Link } from "react-router-dom";

import { toast } from "@/hooks/use-toast";
import { Button } from "@/components/ui/button";
import { useI18n } from "@/contexts/I18nContext";
import {
  Shield,
  BarChart3,
  Globe,
  Code,
  Bell,
  Zap,
  EyeOff,
  Fingerprint,
  ShieldCheck,
  Lock,
  Check,
  ChevronRight,
  Server,
  Wifi,
  Monitor,
  Search,
  TrendingUp,
  ShoppingCart,
  Eye,
  FileSearch,
  Database,
  ArrowRight,
  X as XIcon,
  Timer,
  RefreshCw,
  Layers,
  Smartphone,
  Brain,
  MapPin,
  ShieldCheck as BrandShield,
  Plane,
  Loader2,
} from "lucide-react";
import { useProducts } from "@/hooks/use-backend";

/* ── Data ──────────────────────────────────────────────── */

const PRODUCTS = [
  { name: "Residential Proxies", price: "€0.99", unit: "/GB", icon: Globe, gradient: "from-blue-500 to-indigo-600", link: "/residential-proxies", cta: "hero.getStarted" },
  { name: "Mobile Proxies", price: "€2.95", unit: "/GB", icon: Smartphone, gradient: "from-purple-500 to-pink-500", link: "/mobile-proxies", cta: "hero.getStarted" },
  { name: "Datacenter Proxies", price: "€0.79", unit: "/GB", icon: Server, gradient: "from-green-500 to-emerald-600", link: "/datacenter-proxies", cta: "hero.getStarted" },
  { name: "Static Residential Proxies", price: "€2.99", unit: "/IP", icon: Wifi, gradient: "from-orange-500 to-amber-500", link: "/isp-proxies", cta: "hero.getStarted" },
];

const TOP_LOCATIONS = [
  { name: "United States", flag: "🇺🇸", ips: "9,567,890+" },
  { name: "United Kingdom", flag: "🇬🇧", ips: "3,260,554+" },
  { name: "Germany", flag: "🇩🇪", ips: "3,221,909+" },
  { name: "France", flag: "🇫🇷", ips: "2,661,413+" },
  { name: "Japan", flag: "🇯🇵", ips: "3,345,678+" },
  { name: "Canada", flag: "🇨🇦", ips: "2,789,123+" },
  { name: "Australia", flag: "🇦🇺", ips: "2,123,456+" },
  { name: "South Korea", flag: "🇰🇷", ips: "1,987,654+" },
];

const STATS_KEYS = [
  { value: "10M+", key: "stats.ipPool" },
  { value: "190+", key: "stats.countries" },
  { value: "99.9%", key: "stats.uptime" },
  { value: "<50ms", key: "stats.latency" },
];

const CERTIFICATIONS = ["ISO 27001", "ISO 27018", "SOC 2", "GDPR", "CCPA"];

const PROTOCOLS = [
  { name: "HTTP", desc: "Standard web traffic" },
  { name: "HTTPS", desc: "Encrypted connections" },
  { name: "SOCKS5", desc: "Any TCP protocol" },
];

const COMPETITOR_ROWS = [
  { feature: "No Reverse DNS Lookup", us: true, others: false },
  { feature: "TLS Fingerprint Masking", us: true, others: false },
  { feature: "Zero Fingerprint Headers", us: true, others: false },
  { feature: "DNS Leak Protection", us: true, others: false },
  { feature: "Flat-Rate Pricing (No Tiers)", us: true, others: false },
  { feature: "Money-Back Guarantee", us: true, others: true },
  { feature: "190+ Countries", us: true, others: true },
  { feature: "REST API", us: true, others: true },
  { feature: "Instant Setup (<60s)", us: true, others: false },
  { feature: "SOC 2 & GDPR Certified", us: true, others: true },
];

/* ── Newsletter Component ──────────────────────────────── */

function NewsletterSection() {
  const [email, setEmail] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [subscribed, setSubscribed] = useState(false);
  const { t } = useI18n();

  const handleSubscribe = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!email) return;
    try {
      setSubmitting(true);
      await clientApi.subscribeNewsletter(email);
      setSubscribed(true);
      setEmail("");
      toast({ title: "Inscribed!", description: "Welcome to our newsletter." });
    } catch (err: any) {
      toast({ title: "Error", description: err.message, variant: "destructive" });
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <section className="border-t bg-muted/20 py-10">
      <div className="container mx-auto max-w-xl text-center">
        <h2 className="text-lg font-bold sm:text-xl">{t("section.newsletter")}</h2>
        <p className="mt-1 text-sm text-muted-foreground">{t("section.newsletterSub")}</p>
        <form onSubmit={handleSubscribe} className="mt-4 flex gap-2 justify-center">
          <input
            type="email"
            placeholder="your@email.com"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            required
            className="flex-1 max-w-xs rounded-md border border-border bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary/30"
          />
          <Button type="submit" size="sm" disabled={submitting}>
            {submitting ? "…" : t("section.subscribe")}
          </Button>
        </form>
        <p className="mt-2 text-[10px] text-muted-foreground">{t("section.noSpam")}</p>
      </div>
    </section>
  );
}

/* ── Component ────────────────────────────────────────── */

const PRODUCT_STYLE_MAP: Record<string, { icon: any, gradient: string, link: string }> = {
  rp: { icon: Globe, gradient: "from-blue-500 to-indigo-600", link: "/residential-proxies" },
  mp: { icon: Smartphone, gradient: "from-purple-500 to-pink-500", link: "/mobile-proxies" },
  dc: { icon: Server, gradient: "from-green-500 to-emerald-600", link: "/datacenter-proxies" },
  isp_shared: { icon: Wifi, gradient: "from-cyan-500 to-blue-400", link: "/isp-proxies" },
  isp_private: { icon: Shield, gradient: "from-indigo-600 to-purple-500", link: "/isp-proxies" },
  isp_virgin: { icon: Zap, gradient: "from-amber-400 to-orange-500", link: "/isp-proxies" },
  isp: { icon: Wifi, gradient: "from-orange-500 to-amber-500", link: "/isp-proxies" },
};

export default function Landing() {
  const { t } = useI18n();
  const { data: dbProducts = [], isLoading: productsLoading } = useProducts();

  // Combine DB data with UI styles
  const activeProducts = dbProducts.map(p => {
    const style = PRODUCT_STYLE_MAP[p.type] || { icon: Globe, gradient: "from-slate-500 to-slate-700", link: "/signup" };
    return {
      name: p.name,
      price: `€${p.price}`,
      unit: `/${p.unit || 'GB'}`,
      is_isp: p.type?.startsWith('isp_'),
      icon: style.icon,
      gradient: style.gradient,
      link: style.link,
      cta: "hero.getStarted"
    };
  });

  // Fallback to static if no products or loading
  const displayProducts = activeProducts.length > 0 ? activeProducts : [
    { name: "Residential Proxies", price: "€0.99", unit: "/GB", icon: Globe, gradient: "from-blue-500 to-indigo-600", link: "/residential-proxies", cta: "hero.getStarted" },
    { name: "Mobile Proxies", price: "€2.95", unit: "/GB", icon: Smartphone, gradient: "from-purple-500 to-pink-500", link: "/mobile-proxies", cta: "hero.getStarted" },
    { name: "Datacenter Proxies", price: "€0.79", unit: "/GB", icon: Server, gradient: "from-green-500 to-emerald-600", link: "/datacenter-proxies", cta: "hero.getStarted" },
    { name: "Static Residential Proxies", price: "€2.99", unit: "/IP", icon: Wifi, gradient: "from-orange-500 to-amber-500", link: "/isp-proxies", cta: "hero.getStarted" },
  ];

  const FEATURES = [
    { icon: Shield, title: t("feat.enterpriseSecurity"), desc: t("feat.enterpriseSecurityDesc") },
    { icon: BarChart3, title: t("feat.realTimeAnalytics"), desc: t("feat.realTimeAnalyticsDesc") },
    { icon: Globe, title: t("feat.190countries"), desc: t("feat.190countriesDesc") },
    { icon: Code, title: t("feat.restApi"), desc: t("feat.restApiDesc") },
    { icon: Bell, title: t("feat.smartAlerts"), desc: t("feat.smartAlertsDesc") },
    { icon: Zap, title: t("feat.instantSetup"), desc: t("feat.instantSetupDesc") },
  ];

  const SECURITY_FEATURES = [
    { icon: EyeOff, title: t("sec.noReverseDns"), desc: t("sec.noReverseDnsDesc") },
    { icon: Fingerprint, title: t("sec.zeroFingerprint"), desc: t("sec.zeroFingerprintDesc") },
    { icon: ShieldCheck, title: t("sec.dnsLeak"), desc: t("sec.dnsLeakDesc") },
    { icon: Lock, title: t("sec.tlsMasking"), desc: t("sec.tlsMaskingDesc") },
  ];

  const USE_CASES = [
    { icon: Search, title: t("uc.searchMonitoring"), desc: t("uc.searchMonitoringDesc") },
    { icon: Brain, title: t("uc.aiTraining"), desc: t("uc.aiTrainingDesc") },
    { icon: MapPin, title: t("uc.localizedTesting"), desc: t("uc.localizedTestingDesc") },
    { icon: Database, title: t("uc.dataExtraction"), desc: t("uc.dataExtractionDesc") },
    { icon: BrandShield, title: t("uc.brandProtection"), desc: t("uc.brandProtectionDesc") },
    { icon: Plane, title: t("uc.travelData"), desc: t("uc.travelDataDesc") },
  ];

  const FAQS = [
    { question: t("faq.q1"), answer: t("faq.a1") },
    { question: t("faq.q2"), answer: t("faq.a2") },
    { question: t("faq.q3"), answer: t("faq.a3") },
    { question: t("faq.q4"), answer: t("faq.a4") },
    { question: t("faq.q5"), answer: t("faq.a5") },
    { question: t("faq.q6"), answer: t("faq.a6") },
  ];

  return (
    <>
      <SEOHead
        title="UpgradedProxy — Fast, Undetectable Proxy Infrastructure"
        description="10M+ IPs, 190+ countries, no reverse DNS lookup. Residential, datacenter, ISP, mobile, and SOCKS5 proxies with instant setup and full API control."
        canonical="https://upgraderpx.com"
      />
      <SchemaOrg />
      <SchemaFAQ items={FAQS} />

      {/* Hero */}
      <section className="container flex flex-col items-center justify-center py-14 text-center" style={{ minHeight: 280 }}>
        <div className="mb-4 inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/5 px-3 py-1 text-xs font-medium text-primary">
          <Zap className="h-3 w-3" /> {t("hero.badge")}
        </div>
        <h1 className="text-3xl font-bold tracking-tight sm:text-4xl lg:text-5xl">
          {t("hero.title")}
        </h1>
        <p className="mx-auto mt-3 max-w-xl text-sm text-muted-foreground sm:text-base">
          {t("hero.subtitle")}
        </p>
        <div className="mt-6 flex flex-wrap gap-3 justify-center">
          <Button asChild size="sm"><Link to="/signup">{t("hero.getStarted")}</Link></Button>
          <Button asChild variant="outline" size="sm"><Link to="/pricing">{t("hero.viewPricing")}</Link></Button>
        </div>

        {/* Protocol badges */}
        <div className="mt-5 flex items-center gap-2">
          {PROTOCOLS.map((p) => (
            <span key={p.name} className="rounded border border-border bg-muted/50 px-2 py-0.5 text-[10px] font-semibold text-muted-foreground" title={p.desc}>
              {p.name}
            </span>
          ))}
          <span className="text-[10px] text-muted-foreground">{t("hero.supported")}</span>
        </div>

        <div className="mt-6 flex flex-wrap justify-center gap-6 sm:gap-10">
          {STATS_KEYS.map((s) => (
            <div key={s.key} className="text-center">
              <p className="text-xl font-bold sm:text-2xl">{s.value}</p>
              <p className="text-xs text-muted-foreground">{t(s.key)}</p>
            </div>
          ))}
        </div>
      </section>

      {/* Unique selling points */}
      <section className="content-auto border-t bg-primary/5 py-8">
        <div className="container">
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {[
              { icon: Timer, title: t("usp.samePrice"), desc: t("usp.samePriceDesc") },
              { icon: EyeOff, title: t("usp.undetectable"), desc: t("usp.undetectableDesc") },
              { icon: RefreshCw, title: t("usp.stickyRotating"), desc: t("usp.stickyRotatingDesc") },
              { icon: Layers, title: t("usp.allProtocols"), desc: t("usp.allProtocolsDesc") },
            ].map((u) => (
              <div key={u.title} className="flex items-start gap-3 rounded-lg border border-primary/20 bg-card p-4">
                <u.icon className="mt-0.5 h-5 w-5 shrink-0 text-primary" />
                <div>
                  <h3 className="text-sm font-semibold">{u.title}</h3>
                  <p className="mt-0.5 text-xs text-muted-foreground">{u.desc}</p>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Features */}
      <section className="content-auto border-t bg-muted/20 py-10">
        <div className="container">
          <h2 className="mb-6 text-center text-lg font-bold sm:text-xl">{t("section.whyUs")}</h2>
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {FEATURES.map((f) => (
              <div key={f.title} className="flex items-start gap-3 rounded-md border bg-card p-4" style={{ minHeight: 80 }}>
                <f.icon className="mt-0.5 h-5 w-5 shrink-0 text-primary" />
                <div>
                  <h3 className="text-sm font-semibold">{f.title}</h3>
                  <p className="mt-0.5 text-xs text-muted-foreground">{f.desc}</p>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Security */}
      <section className="content-auto border-t py-10">
        <div className="container">
          <div className="text-center">
            <h2 className="text-lg font-bold sm:text-xl">{t("section.security")}</h2>
            <p className="mt-1 text-xs text-muted-foreground">{t("section.securitySub")}</p>
          </div>
          <div className="mt-6 grid gap-3 sm:grid-cols-2">
            {SECURITY_FEATURES.map((f) => (
              <div key={f.title} className="flex items-start gap-3 rounded-md border border-primary/20 bg-primary/5 p-4" style={{ minHeight: 80 }}>
                <f.icon className="mt-0.5 h-5 w-5 shrink-0 text-primary" />
                <div>
                  <h3 className="text-sm font-semibold">{f.title}</h3>
                  <p className="mt-0.5 text-xs text-muted-foreground">{f.desc}</p>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Integration code snippet */}
      <section className="content-auto border-t bg-muted/20 py-10">
        <div className="container">
          <div className="grid gap-8 lg:grid-cols-2 items-center">
            <div>
              <p className="text-xs font-medium text-primary uppercase tracking-wider">{t("section.devFirst")}</p>
              <h2 className="mt-2 text-xl font-bold sm:text-2xl">{t("section.integrate")}</h2>
              <p className="mt-2 text-sm text-muted-foreground">
                {t("section.integrateSub")}
              </p>
              <ul className="mt-4 space-y-2">
                {[t("int.autoRotate"), t("int.stickySessions"), t("int.geoTargeting"), t("int.authMethods")].map((item) => (
                  <li key={item} className="flex items-center gap-2 text-xs text-muted-foreground">
                    <Check className="h-3 w-3 text-primary shrink-0" /> {item}
                  </li>
                ))}
              </ul>
              <Button asChild size="sm" className="mt-4">
                <Link to="/docs">{t("section.readDocs")} <ArrowRight className="ml-1 h-3 w-3" /></Link>
              </Button>
            </div>
            <div className="rounded-lg border bg-card p-4 font-mono text-xs leading-relaxed overflow-x-auto">
              <p className="text-muted-foreground mb-2"># cURL — Residential proxy with US geo-targeting</p>
              <p><span className="text-primary">curl</span> -x http://user:pass@gate.upgradedproxy.com:7777 \</p>
              <p className="pl-4">-k https://httpbin.org/ip</p>
              <p className="mt-3 text-muted-foreground"># Python — Rotate per request</p>
              <p><span className="text-primary">import</span> requests</p>
              <p className="mt-1">proxies = {"{"}</p>
              <p className="pl-4">"http": "http://user:pass@gate.upgradedproxy.com:7777",</p>
              <p className="pl-4">"https": "http://user:pass@gate.upgradedproxy.com:7777"</p>
              <p>{"}"}</p>
              <p className="mt-1">r = requests.get("https://httpbin.org/ip", proxies=proxies)</p>
              <p><span className="text-primary">print</span>(r.json())</p>
            </div>
          </div>
        </div>
      </section>

      {/* Proxy Solutions */}
      <section className="content-auto border-t py-12" style={{ background: "linear-gradient(180deg, hsl(220 40% 8%) 0%, hsl(220 35% 12%) 100%)" }}>
        <div className="container">
          <div className="grid gap-8 lg:grid-cols-[280px_1fr]">
            {/* Left column */}
            <div className="space-y-4">
              <div className="rounded-xl border border-white/10 bg-white/5 p-5">
                <h2 className="text-lg font-bold text-white">{t("section.proxySolutions")}</h2>
                <p className="mt-2 text-sm text-white/60">{t("section.proxySolutionsSub")}</p>
              </div>
              <div className="rounded-xl border border-white/10 bg-white/5 p-5">
                <h3 className="text-lg font-bold text-white">{t("section.scrapingSolutions")}</h3>
              </div>
            </div>

            {/* Right column — product cards */}
            <div className="space-y-3 relative">
              {productsLoading && (
                <div className="absolute inset-0 flex items-center justify-center bg-transparent z-10">
                  <Loader2 className="h-6 w-6 animate-spin text-primary" />
                </div>
              )}
              {displayProducts.map((p) => (
                <div key={p.name} className="group flex items-center gap-4 rounded-xl border border-white/10 bg-white/5 p-4 transition-colors hover:border-primary/40">
                  <div className={`flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br ${p.gradient}`}>
                    <p.icon className="h-6 w-6 text-white" />
                  </div>
                  <div className="flex-1">
                    <h3 className="text-base font-semibold text-white">{p.name}</h3>
                  </div>
                  <div className="text-right">
                    <span className="text-sm text-white/60">{t("section.from")} </span>
                    <span className="text-xl font-bold text-white">{p.price}</span>
                    <span className="text-sm text-white/60">{p.is_isp ? p.unit : p.unit}</span>
                  </div>
                  <div className="flex flex-col items-end gap-1">
                    <Button asChild size="sm" variant="outline" className="border-primary text-primary hover:bg-primary hover:text-primary-foreground">
                      <Link to="/signup">{t(p.cta)} <ChevronRight className="ml-1 h-3 w-3" /></Link>
                    </Button>
                    <Link to={p.link} className="text-xs text-white/50 hover:text-white transition-colors">
                      {t("section.learnMore")} <ChevronRight className="inline h-3 w-3" />
                    </Link>
                  </div>
                </div>
              ))}
            </div>
          </div>

          {/* Bottom bar */}
          <div className="mt-6 flex flex-wrap items-center justify-between gap-4 rounded-xl border border-white/10 bg-white/5 px-5 py-3">
            <div className="flex items-center gap-2 text-sm text-white/70">
              <Check className="h-4 w-4 text-primary" />
              {t("section.payAsYouGo")}
            </div>
            <div className="flex items-center gap-4">
              <span className="text-sm text-white/70">{t("section.customPlans")}</span>
              <Button asChild variant="outline" size="sm" className="border-primary text-primary">
                <Link to="/pricing">{t("section.contactSupport")} <ChevronRight className="ml-1 h-3 w-3" /></Link>
              </Button>
            </div>
          </div>
        </div>
      </section>

      {/* Use Cases */}
      <section className="content-auto border-t py-14 bg-muted/20">
        <div className="container">
          <div className="text-center">
            <p className="text-xs font-semibold uppercase tracking-widest text-primary">{t("section.useCases")}</p>
            <h2 className="mt-2 text-2xl font-bold sm:text-3xl">{t("section.useCaseTitle")}</h2>
            <p className="mx-auto mt-2 max-w-xl text-sm text-muted-foreground">
              {t("section.useCaseSub")}
            </p>
            <Button asChild variant="outline" size="sm" className="mt-4">
              <Link to="/use-cases">{t("section.viewAllUseCases")} <ChevronRight className="ml-1 h-3 w-3" /></Link>
            </Button>
          </div>
          <div className="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            {USE_CASES.map((uc) => (
              <div key={uc.title} className="flex flex-col items-center text-center">
                <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-primary text-primary-foreground">
                  <uc.icon className="h-6 w-6" />
                </div>
                <h3 className="mt-4 text-base font-bold">{uc.title}</h3>
                <p className="mt-2 text-sm text-muted-foreground leading-relaxed">{uc.desc}</p>
                <Link to="/use-cases" className="mt-3 inline-flex items-center gap-1 text-sm font-medium text-foreground hover:text-primary transition-colors">
                  {t("section.learnMore")} <ChevronRight className="h-4 w-4" />
                </Link>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Competitor Comparison */}
      <section className="content-auto border-t py-10">
        <div className="container mx-auto max-w-2xl">
          <div className="text-center">
            <h2 className="text-lg font-bold sm:text-xl">{t("section.howWeCompare")}</h2>
            <p className="mt-1 text-xs text-muted-foreground">{t("section.compareSub")}</p>
          </div>
          <div className="mt-6 overflow-x-auto">
            <table className="w-full text-xs">
              <thead>
                <tr className="border-b">
                  <th className="pb-2 text-left font-medium text-muted-foreground">{t("comp.feature")}</th>
                  <th className="pb-2 text-center font-semibold text-primary">UpgradedProxy</th>
                  <th className="pb-2 text-center font-medium text-muted-foreground">{t("comp.others")}</th>
                </tr>
              </thead>
              <tbody>
                {COMPETITOR_ROWS.map((row) => (
                  <tr key={row.feature} className="border-b border-border/50">
                    <td className="py-2.5 pr-4 font-medium">{row.feature}</td>
                    <td className="py-2.5 text-center">
                      {row.us ? <Check className="inline h-4 w-4 text-primary" /> : <XIcon className="inline h-4 w-4 text-muted-foreground/40" />}
                    </td>
                    <td className="py-2.5 text-center">
                      {row.others ? <Check className="inline h-4 w-4 text-muted-foreground/60" /> : <XIcon className="inline h-4 w-4 text-muted-foreground/40" />}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </section>

      {/* Locations preview */}
      <section className="border-t bg-muted/20 py-10">
        <div className="container">
          <h2 className="mb-6 text-center text-lg font-bold sm:text-xl">{t("section.globalCoverage")}</h2>
          <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
            {TOP_LOCATIONS.map((loc) => (
              <div key={loc.name} className="flex items-center gap-3 rounded-md border bg-card p-3">
                <span className="text-xl">{loc.flag}</span>
                <div className="flex-1">
                  <p className="text-sm font-medium">{loc.name}</p>
                  <p className="text-xs text-muted-foreground">{loc.ips} IPs</p>
                </div>
                <ChevronRight className="h-3.5 w-3.5 text-muted-foreground/40" />
              </div>
            ))}
          </div>
          <div className="mt-4 text-center">
            <Button asChild variant="outline" size="sm"><Link to="/locations">{t("section.viewAllLocations")}</Link></Button>
          </div>
        </div>
      </section>

      {/* Social Proof / Trust */}
      <section className="border-t py-10">
        <div className="container">
          <div className="grid gap-6 lg:grid-cols-3">
            <div className="rounded-lg border bg-card p-5">
              <div className="flex gap-1 text-primary">{"★★★★★".split("").map((s, i) => <span key={i}>{s}</span>)}</div>
              <p className="mt-2 text-xs text-muted-foreground italic">{t("test.quote1")}</p>
              <p className="mt-2 text-xs font-semibold">{t("test.author1")}</p>
            </div>
            <div className="rounded-lg border bg-card p-5">
              <div className="flex gap-1 text-primary">{"★★★★★".split("").map((s, i) => <span key={i}>{s}</span>)}</div>
              <p className="mt-2 text-xs text-muted-foreground italic">{t("test.quote2")}</p>
              <p className="mt-2 text-xs font-semibold">{t("test.author2")}</p>
            </div>
            <div className="rounded-lg border bg-card p-5">
              <div className="flex gap-1 text-primary">{"★★★★★".split("").map((s, i) => <span key={i}>{s}</span>)}</div>
              <p className="mt-2 text-xs text-muted-foreground italic">{t("test.quote3")}</p>
              <p className="mt-2 text-xs font-semibold">{t("test.author3")}</p>
            </div>
          </div>
        </div>
      </section>

      {/* Certifications */}
      <section className="border-t bg-muted/20 py-10">
        <div className="container">
          <div className="grid gap-6 lg:grid-cols-2 items-center">
            <div>
              <p className="text-xs font-medium text-muted-foreground uppercase tracking-wider">{t("cert.noCornerscut")}</p>
              <h2 className="mt-2 text-xl font-bold sm:text-2xl">{t("cert.certifiedPartners")}</h2>
              <p className="mt-2 text-sm text-muted-foreground">
                {t("cert.certifiedPartnersSub")}
              </p>
            </div>
            <div className="flex flex-wrap gap-3 justify-center lg:justify-end">
              {CERTIFICATIONS.map((cert) => (
                <div key={cert} className="flex items-center justify-center rounded-full border-2 border-muted-foreground/20 h-16 w-16 text-center">
                  <span className="text-[10px] font-bold text-muted-foreground leading-tight">{cert}</span>
                </div>
              ))}
            </div>
          </div>
        </div>
      </section>

      {/* FAQ */}
      <section className="border-t py-10">
        <div className="container mx-auto max-w-2xl">
          <h2 className="mb-4 text-center text-lg font-bold sm:text-xl">{t("section.faq")}</h2>
          <div className="space-y-2">
            {FAQS.map((item) => (
              <details key={item.question} className="group rounded-md border px-4 py-3">
                <summary className="cursor-pointer text-sm font-medium">{item.question}</summary>
                <p className="mt-2 text-xs text-muted-foreground">{item.answer}</p>
              </details>
            ))}
          </div>
        </div>
      </section>

      {/* Contact Section */}
      <section className="border-t py-10">
        <div className="container mx-auto max-w-2xl text-center">
          <h2 className="text-lg font-bold sm:text-xl">{t("section.contact")}</h2>
          <p className="mt-1 text-sm text-muted-foreground">{t("section.contactSub")}</p>
          <div className="mt-6 flex flex-wrap justify-center gap-3">
            <Button asChild variant="outline" size="sm">
              <a href="https://discord.gg/U4NCv5uFNd" target="_blank" rel="noopener noreferrer" className="gap-2">
                <svg viewBox="0 0 24 24" className="h-4 w-4 fill-current"><path d="M20.317 4.37a19.791 19.791 0 00-4.885-1.515.074.074 0 00-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 00-5.487 0 12.64 12.64 0 00-.617-1.25.077.077 0 00-.079-.037A19.736 19.736 0 003.677 4.37a.07.07 0 00-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 00.031.057 19.9 19.9 0 005.993 3.03.078.078 0 00.084-.028c.462-.63.874-1.295 1.226-1.994a.076.076 0 00-.041-.106 13.107 13.107 0 01-1.872-.892.077.077 0 01-.008-.128 10.2 10.2 0 00.372-.292.074.074 0 01.077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 01.078.01c.12.098.246.198.373.292a.077.077 0 01-.006.127 12.299 12.299 0 01-1.873.892.077.077 0 00-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 00.084.028 19.839 19.839 0 006.002-3.03.077.077 0 00.032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 00-.031-.03z" /></svg>
                Discord
              </a>
            </Button>
            <Button asChild variant="outline" size="sm">
              <a href="https://t.me/UpgraderProxy" target="_blank" rel="noopener noreferrer" className="gap-2">
                <svg viewBox="0 0 24 24" className="h-4 w-4 fill-current"><path d="M11.944 0A12 12 0 000 12a12 12 0 0012 12 12 12 0 0012-12A12 12 0 0012 0h-.056zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 01.171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.479.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z" /></svg>
                Telegram
              </a>
            </Button>
            <Button asChild variant="outline" size="sm">
              <a href="https://facebook.com/UpgradedProxy" target="_blank" rel="noopener noreferrer" className="gap-2">
                <svg viewBox="0 0 24 24" className="h-4 w-4 fill-current"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" /></svg>
                Facebook
              </a>
            </Button>
            <Button asChild variant="outline" size="sm">
              <a href="https://linkedin.com/company/upgradedproxy" target="_blank" rel="noopener noreferrer" className="gap-2">
                <svg viewBox="0 0 24 24" className="h-4 w-4 fill-current"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z" /></svg>
                LinkedIn
              </a>
            </Button>
            <Button asChild variant="outline" size="sm">
              <a href="mailto:support@upgradedproxy.com" className="gap-2">
                <svg viewBox="0 0 24 24" className="h-4 w-4 fill-current"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z" /></svg>
                Email Us
              </a>
            </Button>
          </div>
        </div>
      </section>

      {/* Newsletter */}
      <NewsletterSection />

      {/* CTA */}
      <section className="border-t bg-primary/5 py-8">
        <div className="container flex flex-col items-center gap-3 text-center sm:flex-row sm:justify-center">
          <p className="text-sm font-medium">{t("section.readyToStart")}</p>
          <Button asChild size="sm"><Link to="/signup">{t("section.createAccount")}</Link></Button>
        </div>
      </section>
    </>
  );
}
