import { SEOHead } from "@/components/seo/SEOHead";
import { SchemaProduct, SchemaFAQ } from "@/components/seo/SchemaComponents";
import { FAQSection } from "@/components/seo/FAQSection";
import { InternalLinks } from "@/components/seo/InternalLinks";
import { Link } from "react-router-dom";
import { Button } from "@/components/ui/button";
import { Check, Globe, Server, Wifi, Monitor, Shield, Zap } from "lucide-react";
import { useI18n } from "@/contexts/I18nContext";

import { useProducts } from "@/hooks/use-backend";

const PRODUCT_UI_METADATA: Record<string, any> = {
  rp: { id: "residential", desc: "The cheapest residential proxies on the market", unit: "/GB", icon: Globe, color: "bg-red-50 text-red-500 dark:bg-red-500/10", link: "/signup", tab: "residential" },
  dc: { id: "datacenter", desc: "Blazing fast and budget friendly IPs", unit: "/GB", icon: Server, color: "bg-blue-50 text-blue-500 dark:bg-blue-500/10", link: "/signup", tab: "datacenter" },
  mp: { id: "mobile", desc: "Real 3G/4G/5G mobile device connections", unit: "/GB", icon: Monitor, color: "bg-purple-50 text-purple-500 dark:bg-purple-500/10", link: "/signup", tab: "mobile" },
  dc_ipv6: { id: "datacenter-ipv6", desc: "Future-proof high performance IPs", unit: "/GB", icon: Server, color: "bg-green-50 text-green-600 dark:bg-green-500/10", link: "/signup", tab: "datacenter-ipv6" },
  dc_unmetered: { id: "datacenter-unmetered", desc: "Unlimited bandwidth scraping IPs", unit: "/Month", icon: Zap, color: "bg-orange-50 text-orange-500 dark:bg-orange-500/10", link: "/signup", tab: "datacenter-unmetered" },
  isp_shared: { id: "isp-shared", desc: "Shared static residential IPs", unit: "/IP/Month", icon: Wifi, color: "bg-cyan-50 text-cyan-500 dark:bg-cyan-500/10", link: "/signup", tab: "isp-shared" },
  isp_private: { id: "isp-private", desc: "Private dedicated static IPs", unit: "/IP/Month", icon: Shield, color: "bg-indigo-50 text-indigo-500 dark:bg-indigo-500/10", link: "/signup", tab: "isp-private" },
  isp_virgin: { id: "isp-virgin", desc: "Virgin IPs with 0 fraud score", unit: "/IP/Month", icon: Zap, color: "bg-amber-50 text-amber-500 dark:bg-amber-500/10", link: "/signup", tab: "isp-virgin" },
};



const FAQ_ITEMS = [
  { question: "Why is there only one price per product?", answer: "We believe in fair, transparent pricing. Our pay-as-you-go rates are flat — no hidden tiers. Volume plans simply offer lower rates for higher commitment." },
  { question: "What is the minimum purchase?", answer: "The minimum spend per transaction is €5. Minimum quantities vary by product — e.g. 6 GB for residential, 1 GB for premium/mobile, 7 GB for datacenter, and varies by IP type for static." },
  { question: "Can I switch products at any time?", answer: "Yes. You can use any combination of proxy types from your account. Each product is billed at its own rate." },
  { question: "Do you offer a money-back guarantee?", answer: "Yes. We offer a money-back guarantee within the first 3 days if you've used less than 1 GB or 10% of your purchase — no questions asked." },
  { question: "What payment methods do you accept?", answer: "We accept all major credit cards via Stripe, PayPal, and crypto (BTC, LTC, SOL, TRX, BNB). Crypto payments are VAT-free. Card and PayPal payments include 22% VAT." },
  { question: "Are there overage charges?", answer: "No surprise charges. When you reach your balance limit, proxies pause until you top up." },
  { question: "What is your refund policy?", answer: "We offer a money-back guarantee within the first 3 days, no questions asked." },
];

const RELATED = [
  { to: "/residential-proxies", label: "Residential Proxies" },
  { to: "/datacenter-proxies", label: "Datacenter Proxies" },
  { to: "/isp-proxies", label: "ISP Proxies" },
  { to: "/mobile-proxies", label: "Mobile Proxies" },
  { to: "/socks5-proxies", label: "SOCKS5 Proxies" },
  { to: "/locations", label: "Proxy Locations" },
];



export default function Pricing() {
  const { t } = useI18n();
  const { data: backendProducts } = useProducts();

  const products = (backendProducts || []).map((p: any) => {
    const type = p.type?.toLowerCase() || "";
    const meta = PRODUCT_UI_METADATA[type] || PRODUCT_UI_METADATA.dc;
    const isIsp = type.startsWith("isp");

    return {
      ...meta,
      db_id: p.id,
      name: p.name,
      price: `€${Number(p.price).toFixed(2)}`,
      unit: p.unit ? `/${p.unit}` : (isIsp ? "/IP" : meta.unit),
      desc: p.tagline || meta.desc,
      features: p.features || []
    };
  });

  return (
    <>
      <SEOHead
        title="Pricing — Flat-Rate Proxy Plans in EUR"
        description="Transparent proxy pricing in euros. Residential from €0.99/GB, datacenter from €0.79/GB, premium from €2.99/GB, mobile from €2.95/GB. Volume discounts available."
        canonical="https://upgraderpx.com/pricing"
      />
      <SchemaProduct name="UpgradedProxy Residential Proxies" description="54M+ residential proxy IPs with geo-targeting" price="0.99" currency="EUR" />
      <SchemaFAQ items={FAQ_ITEMS} />

      <section className="container py-12 text-center">
        <h1 className="text-3xl font-bold">{t("page.pricingTitle")}</h1>
        <p className="mx-auto mt-2 max-w-lg text-sm text-muted-foreground">{t("page.pricingSub")}</p>
        <div className="mt-4 flex flex-wrap justify-center gap-4 text-xs text-muted-foreground">
          <span className="flex items-center gap-1"><Check className="h-3 w-3 text-primary" /> {t("page.moneyBack")}</span>
          <span className="flex items-center gap-1"><Check className="h-3 w-3 text-primary" /> {t("page.minTx")}</span>
          <span className="flex items-center gap-1"><Check className="h-3 w-3 text-primary" /> {t("page.payOnly")}</span>
        </div>
      </section>

      <section className="container pb-8">
        <h2 className="text-sm font-semibold text-muted-foreground uppercase tracking-wider mb-4">{t("page.ourProxies")}</h2>
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {products.map((p: any) => (
            <Link key={p.db_id} to={p.link} className="group flex items-start gap-4 rounded-lg border bg-card p-5 transition-colors hover:border-primary/40">
              <div className={`rounded-lg p-2.5 ${p.color}`}><p.icon className="h-5 w-5" /></div>
              <div className="flex-1 min-w-0">
                <div className="flex items-baseline justify-between gap-2">
                  <h3 className="text-sm font-semibold">{p.name}</h3>
                  <span className="text-xs text-muted-foreground whitespace-nowrap">
                    {t("section.from")} <span className="font-bold text-foreground">{p.price}{p.unit}</span>
                  </span>
                </div>
                <p className="mt-0.5 text-xs text-muted-foreground">{p.desc}</p>
              </div>
            </Link>
          ))}
          <div className="flex flex-col items-center justify-center rounded-lg border border-dashed bg-card p-5 text-center">
            <span className="text-lg">🛡️</span>
            <h3 className="mt-2 text-sm font-semibold">{t("page.moneyBack")}</h3>
            <p className="mt-1 text-xs text-muted-foreground">3-day refund policy — no questions asked</p>
            <Button asChild size="sm" variant="outline" className="mt-3">
              <Link to="/signup">{t("hero.getStarted")} →</Link>
            </Button>
          </div>
        </div>
      </section>



      <FAQSection items={FAQ_ITEMS} />
      <InternalLinks links={RELATED} />
    </>
  );
}
