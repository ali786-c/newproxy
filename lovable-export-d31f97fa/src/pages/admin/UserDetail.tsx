import { useParams, useNavigate } from "react-router-dom";
import { SEOHead } from "@/components/seo/SEOHead";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { Progress } from "@/components/ui/progress";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import {
    ArrowLeft,
    User as UserIcon,
    Shield,
    ShieldOff,
    Package,
    Key,
    TicketIcon,
    DollarSign,
    Plus,
    Minus,
    Trash2,
    Lock,
    RefreshCw,
    Mail,
    Calendar,
    Copy,
    Terminal,
    Globe,
    Search,
    Check,
    Eye
} from "lucide-react";
import { useState } from "react";
import { toast } from "@/hooks/use-toast";
import {
    useAdminUserDetail,
    useAdminUserOrders,
    useAdminUserActions,
    useProducts,
    useAdminAction
} from "@/hooks/use-backend";
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
    DialogTrigger
} from "@/components/ui/dialog";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";

export default function UserDetail() {
    const { id } = useParams();
    const navigate = useNavigate();
    const { data: user, isLoading: userLoading } = useAdminUserDetail(id!);
    const { data: orders, isLoading: ordersLoading } = useAdminUserOrders(id!);
    const { data: products } = useProducts();
    const { updatePassword, addOrder, deleteOrder, updateRole } = useAdminUserActions();
    const adminAction = useAdminAction();

    const [balanceAmount, setBalanceAmount] = useState("");
    const [balanceReason, setBalanceReason] = useState("");
    const [newPassword, setNewPassword] = useState("");
    const [confirmPassword, setConfirmPassword] = useState("");

    // Manual Order State
    const [selectedProductId, setSelectedProductId] = useState("");
    const [orderQuantity, setOrderQuantity] = useState("1");
    const [orderDays, setOrderDays] = useState("30");
    const [isAddOrderOpen, setIsAddOrderOpen] = useState(false);

    if (userLoading) return <div className="p-8 text-center text-muted-foreground">Loading user details...</div>;
    if (!user) return <div className="p-8 text-center text-muted-foreground">User not found</div>;

    const handleBalanceAdjust = async (isTopUp: boolean) => {
        const amount = parseFloat(balanceAmount);
        if (!amount || amount <= 0) {
            toast({ title: "Invalid amount", variant: "destructive" });
            return;
        }
        try {
            await adminAction.mutateAsync({
                action: "adjust_balance",
                user_id: user.user_id,
                amount: isTopUp ? amount : -amount,
                reason: balanceReason || (isTopUp ? "Admin top-up" : "Admin deduction"),
            });
            setBalanceAmount("");
            setBalanceReason("");
            toast({ title: `Balance ${isTopUp ? "topped up" : "deducted"} successfully` });
        } catch (err: any) {
            toast({ title: "Error", description: err.message, variant: "destructive" });
        }
    };

    const handlePasswordReset = async () => {
        if (newPassword !== confirmPassword) {
            toast({ title: "Passwords do not match", variant: "destructive" });
            return;
        }
        if (newPassword.length < 8) {
            toast({ title: "Password must be at least 8 characters", variant: "destructive" });
            return;
        }
        try {
            await updatePassword.mutateAsync({ id: user.id, data: { password: newPassword, password_confirmation: confirmPassword } });
            setNewPassword("");
            setConfirmPassword("");
            toast({ title: "Password updated successfully" });
        } catch (err: any) {
            toast({ title: "Error", description: err.message, variant: "destructive" });
        }
    };

    const handleAddOrder = async () => {
        if (!selectedProductId) return;
        try {
            await addOrder.mutateAsync({
                id: user.id,
                data: {
                    product_id: parseInt(selectedProductId),
                    quantity: parseInt(orderQuantity),
                    days: parseInt(orderDays)
                }
            });
            setIsAddOrderOpen(false);
            toast({ title: "Product assigned successfully" });
        } catch (err: any) {
            toast({ title: "Error", description: err.message, variant: "destructive" });
        }
    };

    const handleDeleteOrder = async (orderId: number) => {
        if (!confirm("Are you sure you want to delete this order? This will also remove associated proxies.")) return;
        try {
            await deleteOrder.mutateAsync({ orderId, userId: user.id });
            toast({ title: "Order deleted successfully" });
        } catch (err: any) {
            toast({ title: "Error", description: err.message, variant: "destructive" });
        }
    };

    const handleDeleteProxy = async (proxyId: number) => {
        if (!confirm("Are you sure you want to delete this specific proxy record?")) return;
        try {
            await deleteProxy.mutateAsync({ proxyId, userId: user.id });
            toast({ title: "Proxy deleted successfully" });
        } catch (err: any) {
            toast({ title: "Error", description: err.message, variant: "destructive" });
        }
    };

    const [selectedProxy, setSelectedProxy] = useState<any>(null);

    const handleBan = async () => {
        try {
            await adminAction.mutateAsync({
                action: user.is_banned ? "unban_user" : "ban_user",
                user_id: user.user_id,
                ban_reason: user.is_banned ? undefined : "Violated terms of service",
            });
            toast({ title: user.is_banned ? "User unbanned" : "User banned" });
        } catch (err: any) {
            toast({ title: "Error", description: err.message, variant: "destructive" });
        }
    };

    return (
        <>
            <SEOHead title={`Admin — ${user.full_name || user.email}`} noindex />
            <div className="space-y-6">
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="icon" onClick={() => navigate("/admin/users")}>
                        <ArrowLeft className="h-5 w-5" />
                    </Button>
                    <div className="flex-1">
                        <h1 className="text-2xl font-bold">{user.full_name || "User Details"}</h1>
                        <p className="text-sm text-muted-foreground">{user.email}</p>
                    </div>
                    <Badge variant={user.is_banned ? "destructive" : "default"} className="px-3 py-1">
                        {user.role}
                    </Badge>
                </div>

                <Tabs defaultValue="overview" className="w-full">
                    <TabsList className="grid w-full grid-cols-3 max-w-md">
                        <TabsTrigger value="overview">Overview</TabsTrigger>
                        <TabsTrigger value="proxies">Proxies / Products</TabsTrigger>
                        <TabsTrigger value="security">Security</TabsTrigger>
                    </TabsList>

                    <TabsContent value="overview" className="mt-6 space-y-6">
                        <div className="grid gap-6 md:grid-cols-2">
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-lg">Profile Information</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4 pt-0">
                                    <div className="grid grid-cols-2 gap-4 text-sm">
                                        <div className="flex items-center gap-2">
                                            <UserIcon className="h-4 w-4 text-muted-foreground" />
                                            <div>
                                                <p className="text-xs text-muted-foreground">Full Name</p>
                                                <p className="font-medium">{user.full_name || "—"}</p>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Mail className="h-4 w-4 text-muted-foreground" />
                                            <div>
                                                <p className="text-xs text-muted-foreground">Email</p>
                                                <p className="font-medium">{user.email}</p>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Shield className="h-4 w-4 text-muted-foreground" />
                                            <div>
                                                <p className="text-xs text-muted-foreground">Role</p>
                                                <p className="font-medium capitalize">{user.role}</p>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Calendar className="h-4 w-4 text-muted-foreground" />
                                            <div>
                                                <p className="text-xs text-muted-foreground">Joined</p>
                                                <p className="font-medium">{new Date(user.created_at).toLocaleDateString()}</p>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <DollarSign className="h-4 w-4 text-muted-foreground" />
                                            <div>
                                                <p className="text-xs text-muted-foreground">Current Balance</p>
                                                <p className="font-bold text-lg text-primary">${Number(user.balance).toFixed(2)}</p>
                                            </div>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-lg">Balance Adjustment</CardTitle>
                                    <CardDescription>Add or remove funds from user's wallet</CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4 pt-0">
                                    <div className="space-y-2">
                                        <div className="grid grid-cols-2 gap-4">
                                            <div className="space-y-1.5">
                                                <Label>Amount ($)</Label>
                                                <Input
                                                    type="number"
                                                    placeholder="50.00"
                                                    value={balanceAmount}
                                                    onChange={(e) => setBalanceAmount(e.target.value)}
                                                />
                                            </div>
                                            <div className="space-y-1.5">
                                                <Label>Reason (optional)</Label>
                                                <Input
                                                    placeholder="Refund for issue"
                                                    value={balanceReason}
                                                    onChange={(e) => setBalanceReason(e.target.value)}
                                                />
                                            </div>
                                        </div>
                                        <div className="flex gap-2 pt-2">
                                            <Button onClick={() => handleBalanceAdjust(true)} className="flex-1 bg-green-600 hover:bg-green-700">
                                                <Plus className="mr-2 h-4 w-4" /> Top Up
                                            </Button>
                                            <Button onClick={() => handleBalanceAdjust(false)} variant="destructive" className="flex-1">
                                                <Minus className="mr-2 h-4 w-4" /> Deduct
                                            </Button>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        {/* User Statistics Placeholder - You can fetch detailed stats here if needed */}
                    </TabsContent>

                    <TabsContent value="proxies" className="mt-6 space-y-6">
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle className="text-xl">Active Products & Proxies</CardTitle>
                                <CardDescription>Manage the user's active subscriptions and proxy credentials</CardDescription>
                            </div>
                            <Dialog open={isAddOrderOpen} onOpenChange={setIsAddOrderOpen}>
                                <DialogTrigger asChild>
                                    <Button className="bg-primary text-primary-foreground">
                                        <Plus className="mr-2 h-4 w-4" /> Assign New Product
                                    </Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogHeader>
                                        <DialogTitle>Assign Product Manually</DialogTitle>
                                    </DialogHeader>
                                    <div className="space-y-4 py-4 text-sm font-medium">
                                        <div className="space-y-2">
                                            <Label>Select Product</Label>
                                            <Select onValueChange={setSelectedProductId}>
                                                <SelectTrigger>
                                                    <SelectValue placeholder="Choose a product..." />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {products?.map((p: any) => (
                                                        <SelectItem key={p.id} value={String(p.id)}>
                                                            {p.name} (${p.price})
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div className="grid grid-cols-2 gap-4">
                                            <div className="space-y-2">
                                                <Label>Quantity (GB / IPs / Threads)</Label>
                                                <Input
                                                    type="number"
                                                    value={orderQuantity}
                                                    onChange={(e) => setOrderQuantity(e.target.value)}
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label>Duration (Days)</Label>
                                                <Input
                                                    type="number"
                                                    value={orderDays}
                                                    onChange={(e) => setOrderDays(e.target.value)}
                                                />
                                            </div>
                                        </div>
                                    </div>
                                    <DialogFooter>
                                        <Button variant="outline" onClick={() => setIsAddOrderOpen(false)}>Cancel</Button>
                                        <Button onClick={handleAddOrder} disabled={addOrder.isPending}>
                                            {addOrder.isPending ? "Assigning..." : "Confirm Assignment"}
                                        </Button>
                                    </DialogFooter>
                                </DialogContent>
                            </Dialog>
                        </div>

                        <Card>
                            <CardContent className="p-0">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Proxy Credentials</TableHead>
                                            <TableHead>Order</TableHead>
                                            <TableHead>Type</TableHead>
                                            <TableHead>Location</TableHead>
                                            <TableHead>Expires</TableHead>
                                            <TableHead className="text-right">Action</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {ordersLoading ? (
                                            <TableRow><TableCell colSpan={6} className="text-center py-8">Loading orders...</TableCell></TableRow>
                                        ) : orders?.length === 0 ? (
                                            <TableRow><TableCell colSpan={6} className="text-center py-8 text-muted-foreground">No active products found for this user.</TableCell></TableRow>
                                        ) : (
                                            orders?.flatMap((order: any) =>
                                                order.proxies?.map((proxy: any) => (
                                                    <TableRow key={proxy.id}>
                                                        <TableCell>
                                                            <div className="flex flex-col">
                                                                <code className="text-[10px] font-mono font-medium text-foreground bg-muted/50 px-1.5 py-0.5 rounded border border-border/50 w-fit">
                                                                    {proxy.host}:{proxy.port}
                                                                </code>
                                                                <div className="flex items-center gap-2 mt-1">
                                                                    <span className="text-[10px] text-muted-foreground truncate max-w-[120px]">User: {proxy.username}</span>
                                                                </div>
                                                            </div>
                                                        </TableCell>
                                                        <TableCell>
                                                            <div className="flex flex-col">
                                                                <p className="text-xs font-semibold">#{order.id}</p>
                                                                <p className="text-[10px] text-muted-foreground">{new Date(order.created_at).toLocaleDateString()}</p>
                                                            </div>
                                                        </TableCell>
                                                        <TableCell className="capitalize text-[10px] font-bold">
                                                            <Badge variant="outline" className="text-[9px] h-4 px-1 bg-primary/5 border-primary/20 text-primary">
                                                                {order.product?.type || "Proxy"}
                                                            </Badge>
                                                        </TableCell>
                                                        <TableCell>
                                                            <div className="flex items-center gap-1.5 text-xs">
                                                                <span>{proxy.country === 'US' ? '🇺🇸' : proxy.country === 'GB' ? '🇬🇧' : '🌐'}</span>
                                                                <span className="font-medium">{proxy.country}</span>
                                                            </div>
                                                        </TableCell>
                                                        <TableCell className="text-[10px] text-muted-foreground">
                                                            {order.expires_at ? new Date(order.expires_at).toLocaleDateString() : "Never"}
                                                        </TableCell>
                                                        <TableCell className="text-right">
                                                            <div className="flex justify-end gap-1">
                                                                <Button
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    className="h-8 w-8 text-primary hover:text-primary hover:bg-primary/10"
                                                                    onClick={() => setSelectedProxy({ ...proxy, product_name: order.product?.name, type: order.product?.type })}
                                                                >
                                                                    <Eye className="h-4 w-4" />
                                                                </Button>
                                                                <Button
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    className="h-8 w-8 text-destructive hover:text-destructive hover:bg-destructive/10"
                                                                    onClick={() => handleDeleteProxy(proxy.id)}
                                                                >
                                                                    <Trash2 className="h-4 w-4" />
                                                                </Button>
                                                            </div>
                                                        </TableCell>
                                                    </TableRow>
                                                )) || []
                                            )
                                        )}
                                        {/* Fallback for orders without proxies */}
                                        {!ordersLoading && orders?.map((order: any) => (!order.proxies || order.proxies.length === 0) && (
                                            <TableRow key={`order-${order.id}`}>
                                                <TableCell className="text-muted-foreground italic text-xs">No proxies initialized</TableCell>
                                                <TableCell>
                                                    <div className="flex flex-col">
                                                        <p className="text-xs font-semibold">#{order.id}</p>
                                                        <p className="text-[10px] text-muted-foreground">{new Date(order.created_at).toLocaleDateString()}</p>
                                                    </div>
                                                </TableCell>
                                                <TableCell colSpan={3}></TableCell>
                                                <TableCell className="text-right">
                                                    <Button variant="ghost" size="icon" className="h-8 w-8 text-destructive" onClick={() => handleDeleteOrder(order.id)}>
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="security" className="mt-6 space-y-6">
                        <div className="grid gap-6 md:grid-cols-2">
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-lg flex items-center gap-2">
                                        <Lock className="h-5 w-5 text-primary" /> Change Password
                                    </CardTitle>
                                    <CardDescription>Directly update the user's login password</CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4 pt-0">
                                    <div className="space-y-1.5">
                                        <Label>New Password</Label>
                                        <Input
                                            type="password"
                                            placeholder="••••••••"
                                            value={newPassword}
                                            onChange={(e) => setNewPassword(e.target.value)}
                                        />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label>Confirm Password</Label>
                                        <Input
                                            type="password"
                                            placeholder="••••••••"
                                            value={confirmPassword}
                                            onChange={(e) => setConfirmPassword(e.target.value)}
                                        />
                                    </div>
                                    <Button className="w-full" onClick={handlePasswordReset} disabled={updatePassword.isPending}>
                                        <RefreshCw className={`mr-2 h-4 w-4 ${updatePassword.isPending ? "animate-spin" : ""}`} />
                                        Reset User Password
                                    </Button>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-lg flex items-center gap-2">
                                        <ShieldOff className="h-5 w-5 text-destructive" /> Account Restrictions
                                    </CardTitle>
                                    <CardDescription>Ban or restrict user access to the platform</CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4 pt-0">
                                    <div className="rounded-md bg-muted p-4 space-y-3">
                                        <div className="flex items-center justify-between">
                                            <div>
                                                <p className="text-sm font-medium">Status: {user.is_banned ? "Suspended" : "Active"}</p>
                                                <p className="text-xs text-muted-foreground">
                                                    {user.is_banned ? "User cannot login or use APIs" : "User has full access"}
                                                </p>
                                            </div>
                                            <Button
                                                variant={user.is_banned ? "default" : "destructive"}
                                                size="sm"
                                                onClick={handleBan}
                                            >
                                                {user.is_banned ? <Shield className="mr-2 h-4 w-4" /> : <ShieldOff className="mr-2 h-4 w-4" />}
                                                {user.is_banned ? "Unban Account" : "Ban Account"}
                                            </Button>
                                        </div>
                                    </div>

                                    <div className="space-y-2">
                                        <Label>Change Account Role</Label>
                                        <div className="flex gap-2">
                                            <Button
                                                variant={user.role === "admin" ? "default" : "outline"}
                                                className="flex-1"
                                                onClick={() => updateRole.mutate({ id: user.id, role: "admin" })}
                                                disabled={updateRole.isPending}
                                            >
                                                Admin
                                            </Button>
                                            <Button
                                                variant={user.role === "client" ? "default" : "outline"}
                                                className="flex-1"
                                                onClick={() => updateRole.mutate({ id: user.id, role: "client" })}
                                                disabled={updateRole.isPending}
                                            >
                                                Client
                                            </Button>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </TabsContent>
                </Tabs>
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

// ── Components ────────────────────────────────

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

// ── Helpers ───────────────────────────────────

const generateCurl = (p: any) => `curl -x http://${p.username}:${p.password}@${p.host}:${p.port} https://api.ipify.org`;

const generatePython = (p: any) => `import requests

proxies = {
    'http': 'http://${p.username}:${p.password}@${p.host}:${p.port}',
    'https': 'http://${p.username}:${p.password}@${p.host}:${p.port}',
}

response = requests.get('https://api.ipify.org', proxies=proxies)
print(response.text)`;

const generateNode = (p: any) => `const axios = require('axios');

axios.get('https://api.ipify.org', {
    proxy: {
        protocol: 'http',
        host: '${p.host}',
        port: ${p.port},
        auth: {
            username: '${p.username}',
            password: '${p.password}'
        }
    }
})
.then(res => console.log(res.data))
.catch(err => console.error(err));`;
