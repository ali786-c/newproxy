import { SEOHead } from "@/components/seo/SEOHead";
import { Link } from "react-router-dom";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import {
  Globe,
  Key,
  Shield,
  Code,
  Zap,
  Server,
  Wifi,
  Monitor,
  ArrowRight,
  Copy,
  Check,
  Activity,
  CreditCard,
  ShoppingBag,
} from "lucide-react";
import { useState, useCallback } from "react";

function CopyBlock({ code, title }: { code: string; title: string }) {
  const [copied, setCopied] = useState(false);
  const copy = useCallback(() => {
    navigator.clipboard.writeText(code);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  }, [code]);

  return (
    <div className="rounded-lg border bg-card">
      <div className="flex items-center justify-between border-b px-4 py-2">
        <span className="text-xs font-medium text-muted-foreground">{title}</span>
        <button onClick={copy} className="flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground transition-colors">
          {copied ? <Check className="h-3 w-3" /> : <Copy className="h-3 w-3" />}
          {copied ? "Copied" : "Copy"}
        </button>
      </div>
      <pre className="overflow-x-auto p-4 text-xs leading-relaxed font-mono">{code}</pre>
    </div>
  );
}

const API_ENDPOINTS = [
  { method: "GET", path: "/me/balance", desc: "Check your current account balance and credits." },
  { method: "GET", path: "/products", desc: "List available proxy types (Residential, Mobile, etc.) with pricing." },
  { method: "POST", path: "/proxies/generate", desc: "Generate fresh proxy credentials in exchange for balance. Supports idempotency." },
  { method: "GET", path: "/proxies", desc: "List all your past proxy generations and active orders." },
  { method: "GET", path: "/proxies/:id", desc: "Retrieve the list of proxy credentials for a specific order." },
  { method: "GET", path: "/logs", desc: "Audit your API calls and system events for troubleshooting." },
];

const PROXY_SYSTEMS = [
  { icon: Globe, name: "Residential (RP)", protocol: "HTTP/HTTPS/SOCKS5", host: "rp.evomi.com", port: "1000" },
  { icon: Monitor, name: "Mobile (MP)", protocol: "HTTP/HTTPS/SOCKS5", host: "mp.evomi.com", port: "3000" },
  { icon: Server, name: "Datacenter (DC)", protocol: "HTTP/HTTPS/SOCKS5", host: "dcp.evomi.com", port: "2000" },
  { icon: Wifi, name: "ISP Residential", protocol: "HTTP/HTTPS/SOCKS5", host: "isp.evomi.com", port: "3000" },
];

export default function Docs() {
  return (
    <>
      <SEOHead
        title="Pro API Documentation — V1 Reference"
        description="Official API reference for UpgradedProxy. Learn how to programmatically manage proxies, check balance, and automate your workflow."
        canonical="https://upgraderpx.com/docs"
      />

      {/* Hero Section */}
      <section className="container py-16 border-b">
        <div className="max-w-3xl">
          <Badge className="mb-4" variant="secondary">API V1.0 Beta</Badge>
          <h1 className="text-4xl font-bold tracking-tight sm:text-5xl">Professional API (Beta)</h1>
          <p className="mt-6 text-xl text-muted-foreground leading-relaxed">
            Integrate our high-performance proxy infrastructure directly into your custom applications.
            Automate proxy generation, monitor usage, and manage your balance with simple RESTful endpoints.
          </p>
          <div className="mt-8 flex gap-4">
            <Button asChild><Link to="/dashboard">Get API Key</Link></Button>
            <Button variant="outline" asChild><Link to="#endpoints">Explore Endpoints</Link></Button>
          </div>
        </div>
      </section>

      {/* Getting Started */}
      <section className="container py-16 grid gap-12 lg:grid-cols-2">
        <div>
          <h2 className="text-2xl font-bold mb-6 flex items-center gap-2"><Key className="h-6 w-6 text-primary" /> 1. Authentication</h2>
          <p className="text-muted-foreground mb-6">
            Our API uses API keys to authenticate requests. You can generate a hashed API key from your dashboard settings.
            All requests must be made over HTTPS.
          </p>
          <div className="space-y-4">
            <div className="rounded-lg border bg-muted/30 p-4">
              <p className="text-sm font-semibold mb-2">Recommended Header:</p>
              <code className="text-xs font-mono text-primary">X-API-KEY: uproxy_live_...</code>
            </div>
            <div className="rounded-lg border bg-muted/30 p-4">
              <p className="text-sm font-semibold mb-2">Alternative (Bearer Token):</p>
              <code className="text-xs font-mono text-primary">Authorization: Bearer uproxy_live_...</code>
            </div>
          </div>
        </div>

        <div>
          <h2 className="text-2xl font-bold mb-6 flex items-center gap-2"><Code className="h-6 w-6 text-primary" /> 2. Configuration</h2>
          <p className="text-muted-foreground mb-6">
            The API is organized around REST. It has predictable resource-oriented URLs,
            accepts JSON-encoded request bodies, and returns JSON-encoded responses.
          </p>
          <CopyBlock title="Base URL" code="http://localhost/api/v1" />
          <p className="mt-4 text-xs text-muted-foreground italic">Note: Replace localhost with your production domain in production.</p>
        </div>
      </section>

      {/* Core Endpoints */}
      <section id="endpoints" className="bg-muted/30 border-y py-16">
        <div className="container">
          <h2 className="text-3xl font-bold mb-2">API Endpoints</h2>
          <p className="text-muted-foreground mb-10 max-w-2xl text-sm">
            Everything you need to automate your proxy purchasing flow.
          </p>

          <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            {API_ENDPOINTS.map((endpoint) => (
              <Card key={endpoint.path} className="flex flex-col">
                <CardHeader className="pb-2">
                  <div className="flex items-center justify-between mb-2">
                    <Badge variant={endpoint.method === "GET" ? "secondary" : "default"}>{endpoint.method}</Badge>
                    <code className="text-xs font-mono opacity-60">{endpoint.path}</code>
                  </div>
                  <CardTitle className="text-base">{endpoint.desc}</CardTitle>
                </CardHeader>
              </Card>
            ))}
          </div>
        </div>
      </section>

      {/* Proxy Formatting */}
      <section className="container py-16 border-b">
        <h2 className="text-3xl font-bold mb-4">Proxy Usage & Gateway</h2>
        <p className="text-muted-foreground mb-8 max-w-2xl">
          When you generate proxies via the <code>/proxies/generate</code> endpoint, our system returns a list of credentials.
          Use these in your software using the standard <code>host:port:user:pass</code> format.
        </p>

        <div className="grid gap-8 lg:grid-cols-2">
          <div className="space-y-6">
            {PROXY_SYSTEMS.map((sys) => (
              <div key={sys.name} className="flex items-start gap-4 p-4 rounded-xl border bg-card shadow-sm">
                <div className="p-3 rounded-lg bg-primary/10">
                  <sys.icon className="h-6 w-6 text-primary" />
                </div>
                <div>
                  <h3 className="font-bold">{sys.name}</h3>
                  <p className="text-xs text-muted-foreground mb-2">{sys.protocol}</p>
                  <p className="text-sm font-mono"><span className="opacity-50">Host:</span> {sys.host}</p>
                  <p className="text-sm font-mono"><span className="opacity-50">Port:</span> {sys.port}</p>
                </div>
              </div>
            ))}
          </div>

          <div className="space-y-6">
            <h3 className="text-xl font-bold">Dynamic Targeting</h3>
            <p className="text-sm text-muted-foreground">
              Our proxies support advanced geo-targeting and session management baked into the password string returned by the API.
            </p>
            <div className="rounded-xl border p-5 bg-muted/20">
              <h4 className="text-sm font-semibold mb-3">Password Format Detail:</h4>
              <p className="text-xs font-mono bg-background p-3 rounded border mb-4">
                {`{PROXY_KEY}_country-{CODE}_session-{TYPE}`}
              </p>
              <ul className="text-xs space-y-2 text-muted-foreground">
                <li>• <strong>CODE</strong>: ISO country code (e.g. US, GB, CA)</li>
                <li>• <strong>TYPE</strong>: rotating (fresh IP per request) or sticky (keep IP)</li>
              </ul>
            </div>
            <CopyBlock
              title="cURL Example"
              code={`curl -x http://user:pass@host:port https://httpbin.org/ip`}
            />
          </div>
        </div>
      </section>

      {/* Reliability */}
      <section className="container py-16">
        <div className="rounded-3xl bg-primary/5 p-8 lg:p-12 border border-primary/10">
          <div className="grid gap-12 lg:grid-cols-2">
            <div>
              <h2 className="text-3xl font-bold mb-6">Designed for Reliability</h2>
              <div className="space-y-6">
                <div className="flex gap-4">
                  <div className="shrink-0 h-10 w-10 flex items-center justify-center rounded-full bg-primary text-primary-foreground"><Activity className="h-5 w-5" /></div>
                  <div>
                    <h3 className="font-bold">Idempotent Requests</h3>
                    <p className="text-sm text-muted-foreground mt-1">Safely retry generation requests without being charged twice using our <code>Idempotency-Key</code> header.</p>
                  </div>
                </div>
                <div className="flex gap-4">
                  <div className="shrink-0 h-10 w-10 flex items-center justify-center rounded-full bg-primary text-primary-foreground"><CreditCard className="h-5 w-5" /></div>
                  <div>
                    <h3 className="font-bold">Atomic Transactions</h3>
                    <p className="text-sm text-muted-foreground mt-1">Our system ensures balance deduction and proxy provisioning happen atomically or not at all.</p>
                  </div>
                </div>
                <div className="flex gap-4">
                  <div className="shrink-0 h-10 w-10 flex items-center justify-center rounded-full bg-primary text-primary-foreground"><ShoppingBag className="h-5 w-5" /></div>
                  <div>
                    <h3 className="font-bold">Automatic Refunds</h3>
                    <p className="text-sm text-muted-foreground mt-1">If the upstream provider fails to deliver proxies, your balance is automatically refunded instantly.</p>
                  </div>
                </div>
              </div>
            </div>
            <div className="flex items-center justify-center">
              <div className="w-full max-w-sm space-y-4">
                <div className="rounded-2xl border bg-card p-6 shadow-xl rotate-1">
                  <div className="flex items-center gap-2 mb-4">
                    <div className="h-3 w-3 rounded-full bg-red-500" />
                    <div className="h-3 w-3 rounded-full bg-yellow-500" />
                    <div className="h-3 w-3 rounded-full bg-green-500" />
                  </div>
                  <pre className="text-[10px] font-mono leading-relaxed">
                    {`{
  "success": true,
  "data": {
    "order_id": 164,
    "proxies": [
      {
        "host": "rp.evomi.com",
        "port": 1000,
        "username": "up_782",
        "password": "..."
      }
    ]
  }
}`}
                  </pre>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* Final CTA */}
      <section className="container py-20 text-center">
        <h2 className="text-3xl font-bold mb-6">Ready to scale your scraping?</h2>
        <p className="text-muted-foreground mb-10 max-w-xl mx-auto">
          Sign up now to get your API key and starting automating your proxy infrastructure in minutes.
        </p>
        <div className="flex flex-col sm:flex-row gap-4 justify-center">
          <Button size="lg" asChild><Link to="/signup">Create Account <ArrowRight className="ml-2 h-4 w-4" /></Link></Button>
          <Button size="lg" variant="outline" asChild><Link to="/contact">Talk to Support</Link></Button>
        </div>
      </section>
    </>
  );
}
