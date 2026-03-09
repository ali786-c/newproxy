import { SEOHead } from "@/components/seo/SEOHead";
import { Badge } from "@/components/ui/badge";
import { Check, Shield, Globe, Zap, Cpu } from "lucide-react";

export default function About() {
    return (
        <>
            <SEOHead
                title="About Us - UpgradedProxy"
                description="UPGRADERPROXY provides network infrastructure services including proxy routing and IP network solutions designed for businesses that need secure and reliable internet connectivity."
                canonical="https://upgraderproxy.com/about"
            />
            <article className="container py-14">
                <div className="mx-auto max-w-3xl">
                    <Badge variant="outline" className="mb-4">About UpgradedProxy</Badge>
                    <h1 className="text-4xl font-bold tracking-tight text-foreground">Enterprise Network Infrastructure</h1>
                    <p className="mt-4 text-lg text-muted-foreground">
                        Delivering secure, reliable, and high-performance IP network solutions for global businesses.
                    </p>

                    <div className="mt-10 space-y-8">
                        <section className="prose prose-sm dark:prose-invert max-w-none">
                            <p className="text-base leading-relaxed text-foreground/90">
                                <strong>UPGRADERPROXY</strong> provides network infrastructure services including proxy routing and IP network solutions designed for businesses that need secure and reliable internet connectivity. Our services help companies perform tasks such as web data access, cybersecurity testing, network monitoring, performance optimization, and secure data routing through distributed IP infrastructure.
                            </p>
                        </section>

                        <div className="grid gap-6 sm:grid-cols-2 mt-12 pt-8 border-t">
                            <div className="rounded-xl border bg-card p-6 shadow-sm transition-all hover:shadow-md hover:border-primary/20">
                                <Shield className="h-8 w-8 text-primary mb-4" />
                                <h3 className="font-semibold text-lg">Secure Connectivity</h3>
                                <p className="text-sm text-muted-foreground mt-2">Enterprise-grade encryption and secure routing protocols to protect your data flow across global networks.</p>
                            </div>
                            <div className="rounded-xl border bg-card p-6 shadow-sm transition-all hover:shadow-md hover:border-primary/20">
                                <Globe className="h-8 w-8 text-primary mb-4" />
                                <h3 className="font-semibold text-lg">Global IP Network</h3>
                                <p className="text-sm text-muted-foreground mt-2">Distributed infrastructure across 190+ countries ensures low latency and high availability for any request.</p>
                            </div>
                            <div className="rounded-xl border bg-card p-6 shadow-sm transition-all hover:shadow-md hover:border-primary/20">
                                <Zap className="h-8 w-8 text-primary mb-4" />
                                <h3 className="font-semibold text-lg">Performance Optimized</h3>
                                <p className="text-sm text-muted-foreground mt-2">Custom-built routing technology designed for speed, stability, and maximum success rates.</p>
                            </div>
                            <div className="rounded-xl border bg-card p-6 shadow-sm transition-all hover:shadow-md hover:border-primary/20">
                                <Cpu className="h-8 w-8 text-primary mb-4" />
                                <h3 className="font-semibold text-lg">Scalable Solutions</h3>
                                <p className="text-sm text-muted-foreground mt-2">Infrastructure that grows with your business, from simple data access to heavy-duty network stress testing.</p>
                            </div>
                        </div>

                        <section className="mt-16 rounded-2xl bg-primary/5 p-8 border border-primary/10">
                            <h2 className="text-2xl font-bold mb-4 text-center">Our Commitment</h2>
                            <p className="text-center text-muted-foreground max-w-2xl mx-auto mb-8">
                                We are dedicated to providing the most reliable and undetectable proxy network on the market, backed by ethical sourcing and industry-leading security practices.
                            </p>
                            <div className="grid gap-4 sm:grid-cols-2 max-w-xl mx-auto">
                                {[
                                    "100% Opt-in Residential Network",
                                    "24/7 Enterprise Support",
                                    "99.9% Network Uptime Guarantee",
                                    "Real-time Infrastructure Monitoring",
                                    "SOC 2 Type II Compliant Nodes",
                                    "Continuous Performance Audits",
                                ].map((item) => (
                                    <div key={item} className="flex items-center gap-2 text-sm font-medium">
                                        <Check className="h-4 w-4 text-primary shrink-0" />
                                        <span>{item}</span>
                                    </div>
                                ))}
                            </div>
                        </section>
                    </div>
                </div>
            </article>
        </>
    );
}
