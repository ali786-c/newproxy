import { SEOHead } from "@/components/seo/SEOHead";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Link } from "react-router-dom";
import { useState } from "react";
import {
  Globe, Shield, Smartphone, Server,
  Layers, Wifi, Check, ArrowRight, Star,
  BookOpen, FileText, DollarSign, MessageSquare,
  Wallet, Activity, Zap, Gift, CheckCircle,
} from "lucide-react";
import { useAuth } from "@/contexts/AuthContext";
import { useCurrency } from "@/contexts/CurrencyContext";
import { useI18n } from "@/contexts/I18nContext";
import { useUsage, useStats, useProducts } from "@/hooks/use-backend";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api/client";
import { toast } from "@/hooks/use-toast";
import { z } from "zod";

const PRODUCTS = [
  {
    id: "rp",
    name: "Residential Proxies",
    subtitle: "High-quality ethical residential proxies",
    price: "0.99",
    unit: "GB",
    discount: "BEST VALUE",
    popular: true,
    icon: Globe,
    color: "border-destructive",
    features: [
      "Unbeatable Pricing",
      "50M+ real residential IPs from around the world",
      "Free IP rotation, sticky sessions, and geo-targeting",
      "99.9% uptime with outstanding 24/7 support",
    ],
    cta: "Get Started",
    ctaVariant: "default" as const,
  },
  {
    id: "isp",
    name: "Static Residential",
    subtitle: "100% ethical residential proxies",
    price: "2.75",
    unit: "IP",
    icon: Layers,
    features: [
      "Unlimited Bandwidth",
      "Virgin, Private and Shared IPs",
      "99.9% uptime with outstanding 24/7 support",
    ],
    cta: "Get Started",
    ctaVariant: "default" as const,
  },
  {
    id: "dc",
    name: "Datacenter Proxies",
    subtitle: "High-Speed Affordable Datacenter IPs",
    price: "0.79",
    unit: "GB",
    icon: Server,
    features: [
      "200ms response time",
      "Affordable for high-performance scraping",
      "500k+ IPs in 10k+ datacenters worldwide",
      "99.9% uptime with outstanding 24/7 support",
    ],
    cta: "Get Started",
    ctaVariant: "default" as const,
  },
  {
    id: "mp",
    name: "Mobile Proxies",
    subtitle: "Real human phone IPs",
    price: "2.95",
    unit: "GB",
    icon: Smartphone,
    features: [
      "Real human 3G/4G/5G IPs from 195+ countries",
      "No phone farms, no datacenter IPs, no worries",
      "99.9% uptime with outstanding 24/7 support",
      "Unlimited Concurrent Sessions",
    ],
    cta: "Get Started",
    ctaVariant: "default" as const,
  },
  {
    id: "dc_ipv6",
    name: "Datacenter IPv6",
    subtitle: "Future-proof high performance IPs",
    price: "0.59",
    unit: "GB",
    icon: Server,
    features: [
      "Massive pool of clean IPv6 addresses",
      "Avoid IPv4 congestion and blocks",
      "99.9% uptime with outstanding 24/7 support",
      "Best for scraping modern targets",
    ],
    cta: "Get Started",
    ctaVariant: "default" as const,
  },
  {
    id: "dc_unmetered",
    name: "Datacenter (Unmetered)",
    subtitle: "Unlimited bandwidth scraping IPs",
    price: "19.99",
    unit: "Month",
    icon: Server,
    features: [
      "Never worry about traffic usage again",
      "Ideal for high-bandwidth operations",
      "99.9% uptime guarantee",
      "Dedicated high-speed connectivity",
    ],
    cta: "Get Started",
    ctaVariant: "default" as const,
  },
];

const QUICK_LINKS = [
  { label: "API Docs", icon: BookOpen, url: "/docs" },
  { label: "Blog", icon: FileText, url: "/blog" },
  { label: "Earn $$$", icon: DollarSign, url: "/app/referral" },
  { label: "Live Chat", icon: MessageSquare, url: "#" },
];

export default function AppDashboard() {
  const { user } = useAuth();
  const { format } = useCurrency();
  const { t } = useI18n();
  const { data: usage } = useUsage("24h");
  const { data: stats } = useStats();
  const { data: backendProducts, error: backendProductsError } = useProducts();

  console.log("DASHBOARD RENDER - backendProducts:", backendProducts);
  console.log("DASHBOARD RENDER - backendProductsError:", backendProductsError);

  const products = (backendProducts || []).filter((p: any) => !p.is_trial).map((p: any) => {
    const meta = PRODUCTS.find(m => m.id === p.type) || PRODUCTS[0];
    return {
      ...meta,
      db_id: p.id,
      name: p.name,
      price: p.price_cents / 100,
      subtitle: p.tagline || meta.subtitle,
      features: p.features && p.features.length > 0 ? p.features : meta.features,
      type: p.type,
    };
  });

  // Intelligent Sorting for layout
  let topProducts: any[] = [];
  let bottomProducts: any[] = [];

  if (products.length > 0) {
    // Primary sort by sort_order (ASC), then by price
    const sortedProducts = [...products].sort((a, b) => {
      if (a.sort_order !== b.sort_order) return a.sort_order - b.sort_order;
      return a.price - b.price;
    });

    // Separate Residential vs others (if they are for top section)
    const rpProducts = sortedProducts.filter((p: any) => p.type === 'rp');
    const nonRpProducts = sortedProducts.filter((p: any) => p.type !== 'rp');

    if (rpProducts.length >= 2) {
      topProducts = [rpProducts[0], rpProducts[1]];
      // Core Residential gets the badges
      topProducts[0] = { ...topProducts[0], popular: true, discount: "BEST VALUE" };
      // Premium Residential loses default badges
      topProducts[1] = { ...topProducts[1], popular: false, discount: undefined };

      bottomProducts = [...rpProducts.slice(2), ...nonRpProducts];
    } else if (rpProducts.length === 1) {
      topProducts = [rpProducts[0], ...nonRpProducts.slice(0, 1)];
      topProducts[0] = { ...topProducts[0], popular: true, discount: "BEST VALUE" };

      if (topProducts[1]) {
        topProducts[1] = { ...topProducts[1], popular: false, discount: undefined };
      }
      bottomProducts = nonRpProducts.slice(1);
    } else {
      topProducts = products.slice(0, 2);
      bottomProducts = products.slice(2);
      if (topProducts.length > 0) {
        topProducts[0] = { ...topProducts[0], popular: true, discount: "BEST VALUE" };
      }
    }
  }

  const balance = user?.balance ?? 0;
  const usedMb = stats?.bandwidth_used ?? 0;
  const totalMb = stats?.bandwidth_total ?? 0;
  const usedGb = (usedMb / 1024).toFixed(2);
  const totalGb = (totalMb / 1024).toFixed(2);

  const queryClient = useQueryClient();
  const [trialResult, setTrialResult] = useState<any>(null);

  const trialMutation = useMutation({
    mutationFn: () => api.post('/trial/claim', z.any(), {}),
    onSuccess: (data: any) => {
      setTrialResult(data);
      queryClient.invalidateQueries({ queryKey: ['me'] });
      toast({ title: '🎉 Free trial activated!', description: 'Your 20MB residential proxy is ready.' });
    },
    onError: (err: any) => {
      toast({
        title: 'Could not claim trial',
        description: err?.response?.data?.message || err.message || 'Please try again.',
        variant: 'destructive'
      });
    }
  });

  return (
    <>
      <SEOHead title="Dashboard" noindex />

      <div className="space-y-6">
        {!user?.email_verified_at && (
          <Card className="border-warning bg-warning/5 overflow-hidden">
            <CardContent className="p-4 flex flex-col sm:flex-row items-center justify-between gap-4">
              <div className="flex items-center gap-3">
                <div className="rounded-full bg-warning/10 p-2">
                  <Shield className="h-5 w-5 text-warning" />
                </div>
                <div>
                  <p className="font-semibold text-sm">Verify your email address</p>
                  <p className="text-xs text-muted-foreground">Verification is required to claim your free trial and unlock all features.</p>
                </div>
              </div>
              <Button size="sm" variant="outline" className="border-warning text-warning hover:bg-warning/10" asChild>
                <Link to="/app/settings?tab=verification">Verify Now</Link>
              </Button>
            </CardContent>
          </Card>
        )}

        {/* Free Trial Card — Always visible */}
        <Card className="border-emerald-500/40 bg-gradient-to-r from-emerald-950/30 to-emerald-900/10 overflow-hidden">
          <CardContent className="p-5 flex flex-col sm:flex-row items-center justify-between gap-4">
            <div className="flex items-center gap-3">
              <div className="rounded-full bg-emerald-500/15 p-2.5">
                <Gift className="h-6 w-6 text-emerald-500" />
              </div>
              <div>
                <div className="flex items-center gap-2">
                  <p className="font-bold text-sm">Free Trial — 20MB Residential Proxy</p>
                  <Badge className="bg-emerald-500 text-white text-[10px]">FREE</Badge>
                </div>
                <p className="text-xs text-muted-foreground mt-0.5">
                  {user?.has_claimed_trial
                    ? 'You have already activated your free trial. Check your proxies!'
                    : !user?.email_verified_at
                      ? 'Verify your email to claim 20MB of free residential bandwidth — no credit card needed.'
                      : 'Claim 20MB of free residential bandwidth. One-time per account, no credit card needed.'}
                </p>
              </div>
            </div>

            <div className="shrink-0">
              {user?.has_claimed_trial ? (
                <Button variant="outline" size="sm" className="border-emerald-500 text-emerald-600 gap-1.5" asChild>
                  <Link to="/app/proxies/residential"><CheckCircle className="h-4 w-4" />View Trial Proxy</Link>
                </Button>
              ) : !user?.email_verified_at ? (
                <Button variant="outline" size="sm" className="border-warning text-warning hover:bg-warning/10" asChild>
                  <Link to="/app/settings?tab=verification">Verify Email First</Link>
                </Button>
              ) : (
                <Button
                  size="sm"
                  className="bg-emerald-600 hover:bg-emerald-700 text-white gap-1.5"
                  onClick={() => trialMutation.mutate()}
                  disabled={trialMutation.isPending}
                >
                  <Gift className="h-4 w-4" />
                  {trialMutation.isPending ? 'Claiming...' : 'Claim Free Trial'}
                </Button>
              )}
            </div>
          </CardContent>
        </Card>

        {/* Wallet & Stats Row */}
        <div className="grid gap-4 md:grid-cols-3">
          <Card className="bg-primary/5 border-primary/20">
            <CardContent className="pt-6 flex items-center gap-4">
              <div className="rounded-full bg-primary/10 p-3">
                <Wallet className="h-6 w-6 text-primary" />
              </div>
              <div>
                <p className="text-sm text-muted-foreground font-medium">{t("billing.balance")}</p>
                <p className="text-2xl font-bold">{format(balance)}</p>
              </div>
            </CardContent>
          </Card>

          <Card className="bg-success/5 border-success/20">
            <CardContent className="pt-6 flex items-center gap-4">
              <div className="rounded-full bg-success/10 p-3">
                <Activity className="h-6 w-6 text-success" />
              </div>
              <div>
                <p className="text-sm text-muted-foreground font-medium">Used / Total Bandwidth</p>
                <p className="text-2xl font-bold">{usedGb} / {totalGb} GB</p>
              </div>
            </CardContent>
          </Card>

          <Card className="bg-warning/5 border-warning/20">
            <CardContent className="pt-6 flex items-center gap-4">
              <div className="rounded-full bg-warning/10 p-3">
                <Zap className="h-6 w-6 text-warning" />
              </div>
              <div>
                <p className="text-sm text-muted-foreground font-medium">{t("dashboard.statsLast24h")}</p>
                <div className="flex gap-3 text-2xl font-bold">{stats?.active_proxies ?? 0}</div>
              </div>
            </CardContent>
          </Card>
        </div>

        {/* Quality Badges */}
        <div className="flex flex-wrap items-center justify-center gap-4 text-sm text-muted-foreground">
          <span className="flex items-center gap-1.5"><Shield className="h-4 w-4 text-primary" /> Swiss Quality</span>
          <span className="flex items-center gap-1.5"><ArrowRight className="h-4 w-4 text-warning" /> Lightning Fast</span>
          <span className="flex items-center gap-1.5"><Check className="h-4 w-4 text-success" /> 100% Ethical</span>
          <span className="flex items-center gap-1.5"><Star className="h-4 w-4 text-primary" /> Money-Back Guarantee</span>
        </div>

        <div className="grid gap-4 md:grid-cols-2">
          {topProducts.map((product: any) => (
            <ProductCard key={product.db_id} product={product} format={format} t={t} />
          ))}
        </div>

        <div className="grid gap-4 sm:grid-cols-3">
          {bottomProducts.map((product: any) => (
            <ProductCard key={product.db_id} product={product} format={format} t={t} />
          ))}
        </div>

        {/* Quick Links */}
        <div className="flex items-center justify-center gap-2 flex-wrap">
          <span className="text-sm text-muted-foreground mr-2">Looking for something else?</span>
          {QUICK_LINKS.map((link) => (
            <Button key={link.label} variant="outline" size="sm" className="gap-1.5" asChild>
              <Link to={link.url}>
                <link.icon className="h-3.5 w-3.5" />
                {link.label}
              </Link>
            </Button>
          ))}
        </div>

        {/* Testimonial + Guarantee */}
        <div className="grid gap-4 md:grid-cols-2">
          <Card>
            <CardContent className="pt-6 space-y-3">
              <div className="flex items-center gap-1">
                {[...Array(5)].map((_, i) => (
                  <Star key={i} className="h-4 w-4 fill-warning text-warning" />
                ))}
                <span className="ml-2 text-sm text-muted-foreground">4.9/5 from 500+ reviews</span>
              </div>
              <blockquote className="text-sm italic text-muted-foreground">
                "Incredible proxy quality at unmatched prices. The residential pool is massive and bans are virtually non-existent. Best service we've used for competitive intelligence."
              </blockquote>
              <div className="flex items-center gap-2">
                <div className="h-8 w-8 rounded-full bg-primary/10 flex items-center justify-center text-xs font-bold text-primary">MM</div>
                <div>
                  <p className="text-sm font-medium">Michal Marhan</p>
                  <p className="text-xs text-muted-foreground">Software Developer @ deepscout.ai</p>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardContent className="pt-6 space-y-3">
              <div className="flex items-center gap-2">
                <Shield className="h-6 w-6 text-primary" />
                <h3 className="font-bold text-lg">Money Back Guarantee</h3>
              </div>
              <p className="text-sm text-muted-foreground">
                No questions asked. If you used less than 1GB or 10% and you're not satisfied, we'll refund you in full.
              </p>
              <Button variant="outline" size="sm" asChild>
                <Link to="/terms">Learn More</Link>
              </Button>
            </CardContent>
          </Card>
        </div>
      </div>
    </>
  );
}

function ProductCard({ product, format, t }: { product: any, format: (val: number) => string, t: (key: string) => string }) {
  return (
    <Card className={`relative overflow-hidden ${product.popular ? "border-destructive ring-1 ring-destructive/20" : ""}`}>
      {product.popular && (
        <Badge className="absolute top-3 right-3 bg-destructive text-destructive-foreground">POPULAR</Badge>
      )}
      <CardContent className="pt-6 space-y-4">
        <div className="flex items-center gap-3">
          <div className={`rounded-lg p-2 ${product.popular ? "bg-destructive/10" : "bg-primary/10"}`}>
            <product.icon className={`h-5 w-5 ${product.popular ? "text-destructive" : "text-primary"}`} />
          </div>
          <div>
            <h3 className="font-bold">{product.name}</h3>
            <p className="text-xs text-muted-foreground">{product.subtitle}</p>
          </div>
        </div>

        <div className="flex items-baseline gap-1">
          <span className="text-xs text-muted-foreground">{t("section.from")}</span>
          <span className="text-3xl font-bold">{format(Number(product.price))}</span>
          <span className="text-sm text-muted-foreground">/{product.unit}</span>
          {product.discount && (
            <Badge variant="secondary" className="ml-2 bg-success/10 text-success text-[10px]">{product.discount}</Badge>
          )}
        </div>

        <ul className="space-y-1.5">
          {product.features.map((f) => (
            <li key={f} className="flex items-start gap-2 text-sm text-muted-foreground">
              <Check className="mt-0.5 h-3.5 w-3.5 shrink-0 text-success" />
              {f}
            </li>
          ))}
        </ul>

        <div className="flex gap-2">
          <Button className="flex-1" asChild>
            <Link to={`/app/proxies/${product.id}`}>
              {product.cta}
            </Link>
          </Button>
          <Button variant="outline" className="flex-1" asChild>
            <Link to={`/app/proxies/generate`}>
              Learn More
            </Link>
          </Button>
        </div>
      </CardContent>
    </Card>
  );
}
