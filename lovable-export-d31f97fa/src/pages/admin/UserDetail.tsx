import { useParams, useNavigate } from "react-router-dom";
import { SEOHead } from "@/components/seo/SEOHead";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
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
    Calendar
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
                                            <TableHead>Order Info</TableHead>
                                            <TableHead>Type</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead>Qty</TableHead>
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
                                            orders?.map((order: any) => (
                                                <TableRow key={order.id}>
                                                    <TableCell>
                                                        <div>
                                                            <p className="font-medium text-sm">#{order.id} {order.product?.name}</p>
                                                            <p className="text-xs text-muted-foreground">{new Date(order.created_at).toLocaleDateString()}</p>
                                                        </div>
                                                    </TableCell>
                                                    <TableCell className="capitalize text-xs font-semibold">{order.product?.type || "Manual"}</TableCell>
                                                    <TableCell>
                                                        <Badge variant={order.status === "active" ? "default" : "secondary"} className="text-[10px] uppercase">
                                                            {order.status}
                                                        </Badge>
                                                    </TableCell>
                                                    <TableCell className="text-sm font-medium">{order.proxies?.length || 0} units</TableCell>
                                                    <TableCell className="text-xs text-muted-foreground">
                                                        {order.expires_at ? new Date(order.expires_at).toLocaleDateString() : "Never"}
                                                    </TableCell>
                                                    <TableCell className="text-right">
                                                        <Button variant="ghost" size="icon" className="text-destructive hover:text-destructive hover:bg-destructive/10" onClick={() => handleDeleteOrder(order.id)}>
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    </TableCell>
                                                </TableRow>
                                            ))
                                        )}
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
        </>
    );
}
