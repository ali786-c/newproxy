import { useState, useMemo } from "react";
import { useParams, Link } from "react-router-dom";
import { SEOHead } from "@/components/seo/SEOHead";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Copy, Download, Search, LayoutGrid, List as ListIcon, Loader2, Plus, Eye, Check, Terminal, Globe } from "lucide-react";
import { Input } from "@/components/ui/input";
import { Progress } from "@/components/ui/progress";
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from "@/components/ui/dialog";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { useOrders } from "@/hooks/use-backend";
import { toast } from "@/hooks/use-toast";
import { formatProxyLine } from "@/lib/utils";

function generateCurl(p: any) {
    return `curl -x http://${p.username}:${p.password}@${p.host}:${p.port} https://httpbin.org/ip`;
}

function generatePython(p: any) {
    return `import requests\n\nproxies = {\n    "http": "http://${p.username}:${p.password}@${p.host}:${p.port}",\n    "https": "http://${p.username}:${p.password}@${p.host}:${p.port}",\n}\nresponse = requests.get("https://httpbin.org/ip", proxies=proxies)\nprint(response.json())`;
}

function generateNode(p: any) {
    return `const HttpsProxyAgent = require('https-proxy-agent');\n\nconst agent = new HttpsProxyAgent(\n  'http://${p.username}:${p.password}@${p.host}:${p.port}'\n);\n\nfetch('https://httpbin.org/ip', { agent })\n  .then(res => res.json())\n  .then(console.log);`;
}

const TYPE_LABELS: Record<string, string> = {
    "residential": "Residential Proxies",
    "datacenter": "Datacenter Proxies",
    "mobile": "Mobile Proxies",
    "datacenter-ipv6": "Datacenter IPv6",
    "datacenter-unmetered": "Datacenter Unmetered",
    "isp-shared": "Shared Static Residential",
    "isp-private": "Private Static Residential",
    "isp-virgin": "Virgin Static Residential",
};

const DB_TYPE_MAP: Record<string, string> = {
    "residential": "rp",
    "mobile": "mp",
    "datacenter": "dc",
    "datacenter-ipv6": "dc_ipv6",
    "datacenter-unmetered": "dc_unmetered",
    "isp-shared": "isp_shared",
    "isp-private": "isp_private",
    "isp-virgin": "isp_virgin",
};

export default function ProxyList() {
    const { type } = useParams<{ type: string }>();
    const dbType = type ? DB_TYPE_MAP[type] || type : null;
    const { data: orders, isLoading } = useOrders(dbType);
    const [search, setSearch] = useState("");
    const [viewMode, setViewMode] = useState<"table" | "grid">("table");
    const [selectedProxy, setSelectedProxy] = useState<any>(null);

    const title = type ? TYPE_LABELS[type] || "Proxies" : "All Proxies";

    const allProxies = useMemo(() => {
        if (!orders) return [];
        return orders.flatMap((order: any) =>
            (order.proxies || []).map((p: any) => ({
                ...p,
                product_name: order.product?.name || "Unknown",
                expires_at: order.expires_at,
                bandwidth_used: order.bandwidth_used || 0,
                bandwidth_total: order.bandwidth_total || 0,
            }))
        );
    }, [orders]);

    const filteredProxies = useMemo(() => {
        if (!search) return allProxies;
        const s = search.toLowerCase();
        return allProxies.filter((p: any) =>
            p.host.toLowerCase().includes(s) ||
            p.username.toLowerCase().includes(s) ||
            p.country.toLowerCase().includes(s)
        );
    }, [allProxies, search]);

    const copyProxy = (p: any) => {
        const text = formatProxyLine(p);
        navigator.clipboard.writeText(text);
        toast({ title: "Copied", description: "Proxy credentials copied to clipboard." });
    };

    const exportProxies = () => {
        const content = filteredProxies.map((p: any) => formatProxyLine(p)).join("\n");
        const blob = new Blob([content], { type: "text/plain" });
        const url = URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.href = url;
        a.download = `${type || "proxies"}-export.txt`;
        a.click();
        URL.revokeObjectURL(url);
    };

    return (
        <>
            <SEOHead title={title} noindex />
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">{title}</h1>
                        <p className="text-sm text-muted-foreground mt-1">Manage and export your {title.toLowerCase()} list.</p>
                    </div>
                    <Button asChild>
                        <Link to="/app/proxies/generate" className="gap-2">
                            <Plus className="h-4 w-4" /> Generate New
                        </Link>
                    </Button>
                </div>

                <Card>
                    <CardHeader className="pb-3 border-b">
                        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div className="relative max-w-sm w-full">
                                <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    placeholder="Search by host, user, or country..."
                                    className="pl-9"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                />
                            </div>
                            <div className="flex items-center gap-2">
                                <div className="flex items-center border rounded-md p-1 bg-muted/50">
                                    <Button
                                        variant={viewMode === "table" ? "secondary" : "ghost"}
                                        size="icon"
                                        className="h-8 w-8"
                                        onClick={() => setViewMode("table")}
                                    >
                                        <ListIcon className="h-4 w-4" />
                                    </Button>
                                    <Button
                                        variant={viewMode === "grid" ? "secondary" : "ghost"}
                                        size="icon"
                                        className="h-8 w-8"
                                        onClick={() => setViewMode("grid")}
                                    >
                                        <LayoutGrid className="h-4 w-4" />
                                    </Button>
                                </div>
                                <Button variant="outline" size="sm" onClick={exportProxies} disabled={filteredProxies.length === 0} className="gap-2">
                                    <Download className="h-4 w-4" /> Export All
                                </Button>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        {isLoading ? (
                            <div className="flex items-center justify-center p-12">
                                <Loader2 className="h-8 w-8 animate-spin text-primary" />
                            </div>
                        ) : filteredProxies.length === 0 ? (
                            <div className="flex flex-col items-center justify-center p-12 text-center text-muted-foreground">
                                <p>No proxies found matching your criteria.</p>
                                {!search && (
                                    <Button variant="link" asChild className="mt-2">
                                        <Link to="/app/proxies/generate">Generate your first batch now</Link>
                                    </Button>
                                )}
                            </div>
                        ) : viewMode === "table" ? (
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Location</TableHead>
                                            <TableHead>Host:Port</TableHead>
                                            <TableHead>Auth</TableHead>
                                            <TableHead>Usage</TableHead>
                                            <TableHead>Expires</TableHead>
                                            <TableHead className="text-right">Action</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {filteredProxies.map((p: any, i: number) => (
                                            <TableRow key={i}>
                                                <TableCell>
                                                    <div className="flex items-center gap-2">
                                                        <span className="text-lg leading-none" title={p.country}>
                                                            {p.country === 'US' ? '🇺🇸' : p.country === 'GB' ? '🇬🇧' : p.country === 'DE' ? '🇩🇪' : '🌐'}
                                                        </span>
                                                        <span className="text-sm font-medium">{p.country}</span>
                                                    </div>
                                                </TableCell>
                                                <TableCell className="font-mono text-xs">{p.host}:{p.port}</TableCell>
                                                <TableCell className="font-mono text-xs max-w-[150px] truncate" title={`${p.username}:${p.password}`}>
                                                    {p.username}:{p.password}
                                                </TableCell>
                                                <TableCell className="min-w-[120px]">
                                                    {p.product_name.toLowerCase().includes('static') || p.product_name.toLowerCase().includes('isp') ? (
                                                        <span className="text-xs font-medium text-green-600 bg-green-50 px-2 py-0.5 rounded-full">Unlimited</span>
                                                    ) : (
                                                        <div className="space-y-1">
                                                            <div className="flex justify-between text-[10px] font-medium">
                                                                <span>{p.bandwidth_used >= 1024 ? (p.bandwidth_used / 1024).toFixed(2) + ' GB' : Math.round(p.bandwidth_used) + ' MB'}</span>
                                                                <span className="text-muted-foreground">{p.bandwidth_total >= 1024 ? (p.bandwidth_total / 1024).toFixed(2) + ' GB' : Math.round(p.bandwidth_total) + ' MB'}</span>
                                                            </div>
                                                            <Progress value={Math.min(100, (p.bandwidth_used / (p.bandwidth_total || 1)) * 100)} className="h-1" />
                                                        </div>
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <Badge variant="outline" className="text-[10px] font-normal">
                                                        {new Date(p.expires_at).toLocaleDateString()}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <div className="flex items-center justify-end gap-1">
                                                        <Button variant="ghost" size="icon" className="h-8 w-8" onClick={() => setSelectedProxy(p)}>
                                                            <Eye className="h-4 w-4" />
                                                        </Button>
                                                        <Button variant="ghost" size="icon" className="h-8 w-8" onClick={() => copyProxy(p)}>
                                                            <Copy className="h-4 w-4" />
                                                        </Button>
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        ) : (
                            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 p-4">
                                {filteredProxies.map((p: any, i: number) => (
                                    <div key={i} className="border rounded-lg p-3 space-y-2 bg-card hover:border-primary/50 transition-colors">
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-2">
                                                <span className="text-lg">{p.country === 'US' ? '🇺🇸' : p.country === 'GB' ? '🇬🇧' : '🌐'}</span>
                                                <Badge variant="secondary" className="text-[10px] uppercase font-bold">{type || 'Proxy'}</Badge>
                                            </div>
                                            <div className="flex items-center gap-1">
                                                <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => setSelectedProxy(p)}>
                                                    <Eye className="h-3.5 w-3.5" />
                                                </Button>
                                                <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => copyProxy(p)}>
                                                    <Copy className="h-3.5 w-3.5" />
                                                </Button>
                                            </div>
                                        </div>
                                        <div className="font-mono text-xs p-2 bg-muted rounded truncate">
                                            {p.host}:{p.port}
                                        </div>
                                        <div className="space-y-1.5 pt-1">
                                            {p.product_name.toLowerCase().includes('static') || p.product_name.toLowerCase().includes('isp') ? (
                                                <Badge variant="outline" className="w-full justify-center border-green-200 text-green-600 bg-green-50/50">Unlimited Bandwidth</Badge>
                                            ) : (
                                                <>
                                                    <div className="flex justify-between text-[10px] font-medium">
                                                        <span>Used: {p.bandwidth_used >= 1024 ? (p.bandwidth_used / 1024).toFixed(2) + ' GB' : Math.round(p.bandwidth_used) + ' MB'}</span>
                                                        <span className="text-muted-foreground">Limit: {p.bandwidth_total >= 1024 ? (p.bandwidth_total / 1024).toFixed(2) + ' GB' : Math.round(p.bandwidth_total) + ' MB'}</span>
                                                    </div>
                                                    <Progress value={Math.min(100, (p.bandwidth_used / (p.bandwidth_total || 1)) * 100)} className="h-1" />
                                                </>
                                            )}
                                        </div>
                                        <div className="flex items-center justify-between text-[10px] text-muted-foreground">
                                            <span>Expires: {new Date(p.expires_at).toLocaleDateString()}</span>
                                            <span>{p.city || 'Any City'}</span>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            <Dialog open={!!selectedProxy} onOpenChange={() => setSelectedProxy(null)}>
                <DialogContent className="max-w-3xl bg-card border-border/50 shadow-2xl overflow-hidden p-0">
                    <div className="relative h-2 bg-primary"></div>
                    <div className="p-6 space-y-6">
                        <DialogHeader className="pt-2">
                            <div className="flex items-center gap-3">
                                <div className="h-10 w-10 rounded-xl bg-primary/10 flex items-center justify-center">
                                    <Terminal className="h-6 w-6 text-primary" />
                                </div>
                                <div className="text-left">
                                    <DialogTitle className="text-xl font-bold tracking-tight">Proxy Integration</DialogTitle>
                                    <DialogDescription className="text-muted-foreground flex items-center gap-1.5 mt-0.5">
                                        <Badge variant="outline" className="bg-primary/5 text-primary border-primary/20 text-[10px] uppercase font-bold px-1.5 py-0">
                                            {selectedProxy?.product_name || 'Proxy Batch'}
                                        </Badge>
                                        Credentials & Snippets
                                    </DialogDescription>
                                </div>
                            </div>
                        </DialogHeader>

                        {selectedProxy && (
                            <div className="space-y-6">
                                {/* Credentials Grid */}
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <CredentialCard
                                        label="Host:Port"
                                        value={`${selectedProxy.host}:${selectedProxy.port}`}
                                        icon={<Globe className="h-3.5 w-3.5" />}
                                    />
                                    <CredentialCard
                                        label="Country"
                                        value={selectedProxy.country}
                                        subValue={selectedProxy.country === 'US' ? 'United States' : selectedProxy.country === 'GB' ? 'United Kingdom' : 'Global'}
                                        icon={<span>{selectedProxy.country === 'US' ? '🇺🇸' : selectedProxy.country === 'GB' ? '🇬🇧' : '🌐'}</span>}
                                    />
                                    <CredentialCard
                                        label="Username"
                                        value={selectedProxy.username}
                                        icon={<Search className="h-3.5 w-3.5" />}
                                    />
                                    <CredentialCard
                                        label="Password"
                                        value={selectedProxy.password}
                                        icon={<Check className="h-3.5 w-3.5" />}
                                    />
                                </div>

                                {/* Integration Tabs */}
                                <div className="space-y-4">
                                    <h4 className="text-xs font-bold uppercase tracking-widest text-muted-foreground/70">Integration Snippets</h4>
                                    <Tabs defaultValue="curl" className="w-full">
                                        <TabsList className="w-full bg-muted/50 p-1 rounded-xl h-11">
                                            <TabsTrigger value="curl" className="flex-1 rounded-lg data-[state=active]:bg-card data-[state=active]:shadow-sm">cURL</TabsTrigger>
                                            <TabsTrigger value="python" className="flex-1 rounded-lg data-[state=active]:bg-card data-[state=active]:shadow-sm">Python</TabsTrigger>
                                            <TabsTrigger value="node" className="flex-1 rounded-lg data-[state=active]:bg-card data-[state=active]:shadow-sm">Node.js</TabsTrigger>
                                        </TabsList>
                                        <div className="mt-4 ring-1 ring-border/50 rounded-2xl overflow-hidden shadow-inner">
                                            <TabsContent value="curl" className="m-0 border-none">
                                                <SnippetBlock code={generateCurl(selectedProxy)} />
                                            </TabsContent>
                                            <TabsContent value="python" className="m-0 border-none">
                                                <SnippetBlock code={generatePython(selectedProxy)} />
                                            </TabsContent>
                                            <TabsContent value="node" className="m-0 border-none">
                                                <SnippetBlock code={generateNode(selectedProxy)} />
                                            </TabsContent>
                                        </div>
                                    </Tabs>
                                </div>

                                <div className="flex justify-end pt-2">
                                    <Button variant="outline" onClick={() => setSelectedProxy(null)} className="rounded-xl px-8 border-border/50 hover:bg-muted/50">
                                        Close Details
                                    </Button>
                                </div>
                            </div>
                        )}
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}

function CredentialCard({ label, value, subValue, icon }: { label: string, value: string, subValue?: string, icon: React.ReactNode }) {
    return (
        <div className="group relative p-4 rounded-2xl border border-border/50 bg-muted/20 hover:bg-muted/40 hover:border-primary/30 transition-all duration-300 shadow-sm">
            <div className="flex items-center justify-between mb-2">
                <div className="flex items-center gap-2 text-muted-foreground/70 font-bold text-[10px] uppercase tracking-wider">
                    {icon}
                    {label}
                </div>
                <Button
                    variant="ghost"
                    size="icon"
                    className="h-7 w-7 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity"
                    onClick={() => {
                        navigator.clipboard.writeText(value);
                        toast({ title: "Copied", description: `${label} copied.` });
                    }}
                >
                    <Copy className="h-3.5 w-3.5" />
                </Button>
            </div>
            <div className="flex flex-col">
                <code className="text-sm font-mono font-medium break-all tracking-tight text-foreground select-all">
                    {value}
                </code>
                {subValue && (
                    <span className="text-[10px] text-muted-foreground font-medium mt-1">{subValue}</span>
                )}
            </div>
        </div>
    );
}

function SnippetBlock({ code }: { code: string }) {
    return (
        <div className="relative group">
            <div className="absolute top-3 right-3 z-10">
                <Button
                    variant="secondary"
                    size="sm"
                    className="h-8 gap-2 bg-background/50 backdrop-blur-md border border-border/50 hover:bg-background/80 transition-all opacity-0 group-hover:opacity-100"
                    onClick={() => {
                        navigator.clipboard.writeText(code);
                        toast({ title: "Copied", description: "Code snippet copied." });
                    }}
                >
                    <Copy className="h-3.5 w-3.5" />
                    Copy
                </Button>
            </div>
            <pre className="p-6 bg-muted/30 font-mono text-xs leading-relaxed whitespace-pre-wrap break-all selection:bg-primary/30 min-h-[120px] max-h-[400px]">
                {code}
            </pre>
        </div>
    );
}
