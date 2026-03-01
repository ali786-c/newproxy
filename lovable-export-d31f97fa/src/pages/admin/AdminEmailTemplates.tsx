import { useState } from "react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { api, MessageSchema } from "@/lib/api/client";
import { z } from "zod";
import { SEOHead } from "@/components/seo/SEOHead";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { Textarea } from "@/components/ui/textarea";
import { toast } from "@/hooks/use-toast";
import {
    Mail,
    Search,
    Save,
    Eye,
    Send,
    ArrowLeft,
    Info,
    CheckCircle2,
    XCircle,
    Plus,
    Trash2
} from "lucide-react";
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
    DialogDescription,
} from "@/components/ui/dialog";
import {
    Tabs,
    TabsContent,
    TabsList,
    TabsTrigger,
} from "@/components/ui/tabs";
import { Badge } from "@/components/ui/badge";

const EmailTemplateSchema = z.object({
    id: z.number().or(z.string()),
    key: z.string(),
    name: z.string(),
    subject: z.string(),
    body: z.string(),
    format: z.string(),
    is_active: z.union([z.boolean(), z.number()]),
    variables: z.array(z.string()).nullable().optional(),
    description: z.string().nullable().optional(),
});

type EmailTemplate = z.infer<typeof EmailTemplateSchema>;

export default function AdminEmailTemplates() {
    const queryClient = useQueryClient();
    const [selectedTemplate, setSelectedTemplate] = useState<EmailTemplate | null>(null);
    const [search, setSearch] = useState("");
    const [isPreviewOpen, setIsPreviewOpen] = useState(false);
    const [previewContent, setPreviewContent] = useState<any>(null);
    const [isTestSendOpen, setIsTestSendOpen] = useState(false);
    const [testEmail, setTestEmail] = useState("");

    // Create State
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [newTemplate, setNewTemplate] = useState({
        key: "",
        name: "",
        subject: "",
        body: "# Welcome!\n\nNew template content here.",
        format: "markdown",
        description: ""
    });

    const { data: templates = [], isLoading } = useQuery({
        queryKey: ["admin-email-templates"],
        queryFn: () => api.get("/admin/email-templates", z.array(EmailTemplateSchema)),
    });

    const createMutation = useMutation({
        mutationFn: (data: any) => api.post("/admin/email-templates", z.any(), data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ["admin-email-templates"] });
            toast({ title: "Created", description: "New template added successfully." });
            setIsCreateOpen(false);
            setNewTemplate({ key: "", name: "", subject: "", body: "# Welcome!\n\nNew template content here.", format: "markdown", description: "" });
        },
        onError: (e: any) => toast({ title: "Error", description: e.message, variant: "destructive" }),
    });

    const updateMutation = useMutation({
        mutationFn: (data: Partial<EmailTemplate>) =>
            api.put(`/admin/email-templates/${selectedTemplate?.key}`, z.any(), data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ["admin-email-templates"] });
            toast({ title: "Success", description: "Template updated successfully." });
        },
    });

    const deleteMutation = useMutation({
        mutationFn: (key: string) => api.delete(`/admin/email-templates/${key}`, z.any()),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ["admin-email-templates"] });
            toast({ title: "Deleted", description: "Template removed." });
            setSelectedTemplate(null);
        },
    });

    const previewMutation = useMutation({
        mutationFn: (data: any) =>
            api.post(`/admin/email-templates/${selectedTemplate?.key}/preview`, z.any(), data),
        onSuccess: (data) => {
            setPreviewContent(data);
            setIsPreviewOpen(true);
        },
    });

    const testSendMutation = useMutation({
        mutationFn: (email: string) =>
            api.post(`/admin/email-templates/${selectedTemplate?.key}/test`, MessageSchema, { email }),
        onSuccess: () => {
            toast({ title: "Sent!", description: "Test email has been dispatched." });
            setIsTestSendOpen(false);
        },
    });

    const filteredTemplates = templates.filter(t =>
        t.name.toLowerCase().includes(search.toLowerCase()) ||
        t.key.toLowerCase().includes(search.toLowerCase())
    );

    const handleSave = () => {
        if (!selectedTemplate) return;
        updateMutation.mutate({
            subject: selectedTemplate.subject,
            body: selectedTemplate.body,
            is_active: !!selectedTemplate.is_active,
            format: selectedTemplate.format,
        });
    };

    const handlePreview = () => {
        if (!selectedTemplate) return;
        previewMutation.mutate({
            subject: selectedTemplate.subject,
            body: selectedTemplate.body,
            format: selectedTemplate.format
        });
    };

    const handleDelete = () => {
        if (!selectedTemplate) return;
        if (confirm("Are you sure you want to delete this template? This cannot be undone.")) {
            deleteMutation.mutate(selectedTemplate.key);
        }
    };

    if (isLoading) return <div className="p-8 text-center">Loading templates...</div>;

    if (selectedTemplate) {
        return (
            <div className="space-y-6 animate-in fade-in slide-in-from-left-4 duration-300">
                <SEOHead title={`Edit ${selectedTemplate.name}`} noindex />

                <div className="flex items-center justify-between gap-4">
                    <div className="flex items-center gap-2">
                        <Button variant="ghost" size="icon" onClick={() => setSelectedTemplate(null)}>
                            <ArrowLeft className="h-4 w-4" />
                        </Button>
                        <div>
                            <h1 className="text-2xl font-bold">{selectedTemplate.name}</h1>
                            <p className="text-sm text-muted-foreground">{selectedTemplate.description || "Manage system email content"}</p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button variant="outline" onClick={handlePreview}>
                            <Eye className="mr-2 h-4 w-4" /> Preview
                        </Button>
                        <Button variant="outline" onClick={() => setIsTestSendOpen(true)}>
                            <Send className="mr-2 h-4 w-4" /> Test Send
                        </Button>
                        <Button onClick={handleSave} disabled={updateMutation.isPending}>
                            <Save className="mr-2 h-4 w-4" /> Save Changes
                        </Button>
                        <Button variant="ghost" className="text-destructive hover:bg-destructive/10" onClick={handleDelete}>
                            <Trash2 className="h-4 w-4" />
                        </Button>
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="lg:col-span-2 space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Email Content</CardTitle>
                                <CardDescription>Edit the subject and body of the template.</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-2">
                                    <Label>Subject Line</Label>
                                    <Input
                                        value={selectedTemplate.subject}
                                        onChange={e => setSelectedTemplate({ ...selectedTemplate, subject: e.target.value })}
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label>Body Content (Markdown Supported)</Label>
                                    <Textarea
                                        rows={15}
                                        className="font-mono text-sm leading-relaxed"
                                        value={selectedTemplate.body}
                                        onChange={e => setSelectedTemplate({ ...selectedTemplate, body: e.target.value })}
                                    />
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Info className="h-4 w-4" /> Variables
                                </CardTitle>
                                <CardDescription>Use these tags in the body or subject.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="flex flex-wrap gap-2">
                                    {selectedTemplate.variables?.map(v => (
                                        <Badge key={v} variant="secondary" className="font-mono text-[10px]">
                                            {"{{" + v + "}}"}
                                        </Badge>
                                    ))}
                                    {!selectedTemplate.variables?.length && <p className="text-xs text-muted-foreground italic">No variables defined for this template.</p>}
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Settings</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="flex items-center justify-between">
                                    <div className="space-y-0.5">
                                        <Label>Active Status</Label>
                                        <p className="text-[10px] text-muted-foreground">Toggle to enable/disable this email.</p>
                                    </div>
                                    <Switch
                                        checked={!!selectedTemplate.is_active}
                                        onCheckedChange={v => setSelectedTemplate({ ...selectedTemplate, is_active: v })}
                                    />
                                </div>
                                <div className="space-y-1.5 pt-2 border-t">
                                    <Label className="text-xs">Technical Key</Label>
                                    <code className="block text-[10px] bg-muted p-1 rounded font-mono">{selectedTemplate.key}</code>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>

                {/* Preview Dialog */}
                <Dialog open={isPreviewOpen} onOpenChange={setIsPreviewOpen}>
                    <DialogContent className="max-w-4xl max-h-[90vh] overflow-hidden flex flex-col">
                        <DialogHeader>
                            <DialogTitle>Email Preview</DialogTitle>
                        </DialogHeader>
                        <Tabs defaultValue="rendered" className="flex-1 overflow-hidden flex flex-col">
                            <TabsList className="grid w-full grid-cols-2">
                                <TabsTrigger value="rendered">Preview</TabsTrigger>
                                <TabsTrigger value="source">HTML Source</TabsTrigger>
                            </TabsList>
                            <TabsContent value="rendered" className="flex-1 overflow-auto bg-white rounded-md border mt-2 p-4">
                                <div className="mb-4 border-b pb-2">
                                    <p className="text-xs text-neutral-500 font-semibold uppercase">Subject:</p>
                                    <p className="text-sm font-medium">{previewContent?.subject}</p>
                                </div>
                                <div dangerouslySetInnerHTML={{ __html: previewContent?.html }} />
                            </TabsContent>
                            <TabsContent value="source" className="flex-1 overflow-hidden mt-2">
                                <Textarea readOnly className="h-full font-mono text-xs" value={previewContent?.html} />
                            </TabsContent>
                        </Tabs>
                    </DialogContent>
                </Dialog>

                {/* Test Send Dialog */}
                <Dialog open={isTestSendOpen} onOpenChange={setIsTestSendOpen}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Send Test Email</DialogTitle>
                        </DialogHeader>
                        <div className="space-y-4 py-4">
                            <div className="space-y-2">
                                <Label>Destination Email Address</Label>
                                <Input
                                    placeholder="admin@example.com"
                                    value={testEmail}
                                    onChange={e => setTestEmail(e.target.value)}
                                />
                            </div>
                            <p className="text-xs text-muted-foreground">
                                This will send a live email using your current SMTP settings.
                            </p>
                        </div>
                        <DialogFooter>
                            <Button variant="outline" onClick={() => setIsTestSendOpen(false)}>Cancel</Button>
                            <Button onClick={() => testSendMutation.mutate(testEmail)} disabled={testSendMutation.isPending}>
                                {testSendMutation.isPending ? "Sending..." : "Send Now"}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        );
    }

    return (
        <>
            <SEOHead title="Admin — Email Templates" noindex />
            <div className="space-y-6">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold">Email Templates</h1>
                        <p className="text-sm text-muted-foreground">Manage automated system notifications and branding.</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <div className="relative w-64">
                            <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                            <Input
                                placeholder="Search templates..."
                                className="pl-9"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                            />
                        </div>
                        <Button onClick={() => setIsCreateOpen(true)}>
                            <Plus className="mr-2 h-4 w-4" /> Create New
                        </Button>
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {filteredTemplates.map((template) => (
                        <Card key={template.id} className="group hover:border-primary/50 transition-colors">
                            <CardHeader className="pb-3">
                                <div className="flex items-start justify-between">
                                    <div className="p-2 bg-primary/5 rounded-lg text-primary">
                                        <Mail className="h-5 w-5" />
                                    </div>
                                    <Badge variant={template.is_active ? "default" : "secondary"}>
                                        {template.is_active ? "Active" : "Inactive"}
                                    </Badge>
                                </div>
                                <CardTitle className="mt-4">{template.name}</CardTitle>
                                <CardDescription className="line-clamp-2 mt-1">{template.description || "System notification template"}</CardDescription>
                            </CardHeader>
                            <CardContent className="pb-4 pt-0">
                                <div className="flex items-center gap-2 text-[10px] text-muted-foreground font-mono">
                                    <code className="bg-muted px-1 rounded">{template.key}</code>
                                </div>
                            </CardContent>
                            <div className="p-4 border-t bg-muted/5 group-hover:bg-muted/10 transition-colors flex justify-end">
                                <Button size="sm" onClick={() => setSelectedTemplate(template)}>
                                    Configure
                                </Button>
                            </div>
                        </Card>
                    ))}
                    {filteredTemplates.length === 0 && (
                        <div className="col-span-full p-12 text-center border rounded-lg border-dashed">
                            <Mail className="h-10 w-10 mx-auto text-muted-foreground/50 mb-4" />
                            <h3 className="text-lg font-medium">No templates found</h3>
                            <p className="text-sm text-muted-foreground">Try adjusting your search or create a new template.</p>
                        </div>
                    )}
                </div>
            </div>

            {/* Create Template Dialog */}
            <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
                <DialogContent className="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Create New Email Template</DialogTitle>
                        <DialogDescription>Define a new template. You'll need to hook this key into your backend logic.</DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-4 py-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label>Template Name (Internal)</Label>
                            <Input placeholder="e.g. Order Received" value={newTemplate.name} onChange={e => setNewTemplate({ ...newTemplate, name: e.target.value })} />
                        </div>
                        <div className="space-y-2">
                            <Label>Technical Key (Unique)</Label>
                            <Input placeholder="e.g. order_received" value={newTemplate.key} onChange={e => setNewTemplate({ ...newTemplate, key: e.target.value })} />
                        </div>
                        <div className="col-span-full space-y-2">
                            <Label>Subject Line</Label>
                            <Input placeholder="Your order #{{order.id}} has been received" value={newTemplate.subject} onChange={e => setNewTemplate({ ...newTemplate, subject: e.target.value })} />
                        </div>
                        <div className="col-span-full space-y-2">
                            <Label>Description</Label>
                            <Input placeholder="Sent when a customer makes a successful purchase." value={newTemplate.description} onChange={e => setNewTemplate({ ...newTemplate, description: e.target.value })} />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setIsCreateOpen(false)}>Cancel</Button>
                        <Button disabled={!newTemplate.key || !newTemplate.name || createMutation.isPending} onClick={() => createMutation.mutate(newTemplate)}>
                            {createMutation.isPending ? "Creating..." : "Save Template"}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
