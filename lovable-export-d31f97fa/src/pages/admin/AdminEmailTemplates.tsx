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
    Plus,
    Trash2,
    Code2,
    FileText
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";

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

    if (isLoading) return <div className="p-8 text-center text-muted-foreground animate-pulse">Loading templates...</div>;

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
                        <Card className="overflow-hidden border-primary/10">
                            <CardHeader className="bg-muted/30 pb-4">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <CardTitle>Email Content</CardTitle>
                                        <CardDescription>Edit the subject and body of the template.</CardDescription>
                                    </div>
                                    <div className="bg-background/80 backdrop-blur border rounded-lg p-1 flex gap-1 shadow-sm">
                                        <Button
                                            variant={selectedTemplate.format === 'markdown' ? 'secondary' : 'ghost'}
                                            size="sm"
                                            className="h-7 text-xs px-2"
                                            onClick={() => setSelectedTemplate({ ...selectedTemplate, format: 'markdown' })}
                                        >
                                            <FileText className="h-3 w-3 mr-1.5" /> Markdown
                                        </Button>
                                        <Button
                                            variant={selectedTemplate.format === 'html' ? 'secondary' : 'ghost'}
                                            size="sm"
                                            className="h-7 text-xs px-2"
                                            onClick={() => setSelectedTemplate({ ...selectedTemplate, format: 'html' })}
                                        >
                                            <Code2 className="h-3 w-3 mr-1.5" /> HTML Code
                                        </Button>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-4 pt-6">
                                <div className="space-y-2">
                                    <Label className="text-sm font-semibold">Subject Line</Label>
                                    <Input
                                        className="text-lg font-medium border-primary/20 focus-visible:ring-primary/20"
                                        value={selectedTemplate.subject}
                                        onChange={e => setSelectedTemplate({ ...selectedTemplate, subject: e.target.value })}
                                        placeholder="Enter email subject..."
                                    />
                                </div>
                                <div className="space-y-2">
                                    <div className="flex items-center justify-between">
                                        <Label className="text-sm font-semibold">
                                            {selectedTemplate.format === 'markdown' ? 'Body (Markdown Supported)' : 'Body (Raw HTML Code)'}
                                        </Label>
                                        <Badge variant="outline" className="text-[10px] font-mono text-muted-foreground">
                                            {selectedTemplate.format.toUpperCase()} MODE
                                        </Badge>
                                    </div>
                                    <Textarea
                                        rows={20}
                                        className="font-mono text-sm leading-relaxed border-primary/10 focus-visible:ring-primary/20 resize-none min-h-[400px]"
                                        value={selectedTemplate.body}
                                        onChange={e => setSelectedTemplate({ ...selectedTemplate, body: e.target.value })}
                                        placeholder={selectedTemplate.format === 'markdown' ? "# Type your markdown here..." : "<html>\n  <body>\n    <!-- Paste your HTML here -->\n  </body>\n</html>"}
                                    />
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <div className="space-y-6">
                        <Card className="border-primary/10">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Info className="h-4 w-4 text-primary" /> Available Variables
                                </CardTitle>
                                <CardDescription>Click to copy tags to your clipboard.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="flex flex-wrap gap-2">
                                    {selectedTemplate.variables?.map(v => (
                                        <button
                                            key={v}
                                            onClick={() => {
                                                navigator.clipboard.writeText("{{" + v + "}}");
                                                toast({ title: "Copied!", description: `{{${v}}} copied to clipboard.` });
                                            }}
                                            className="font-mono text-[10px] bg-muted hover:bg-primary/10 hover:text-primary border transition-colors rounded px-2 py-1"
                                        >
                                            {"{{" + v + "}}"}
                                        </button>
                                    ))}
                                    {!selectedTemplate.variables?.length && <p className="text-xs text-muted-foreground italic">No variables defined for this template.</p>}
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="border-primary/10">
                            <CardHeader>
                                <CardTitle className="text-base text-primary">Template Settings</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="flex items-center justify-between p-2 rounded-lg bg-muted/20">
                                    <div className="space-y-0.5">
                                        <Label className="text-sm">Active Status</Label>
                                        <p className="text-[10px] text-muted-foreground">Toggle to enable/disable this email.</p>
                                    </div>
                                    <Switch
                                        checked={!!selectedTemplate.is_active}
                                        onCheckedChange={v => setSelectedTemplate({ ...selectedTemplate, is_active: v })}
                                    />
                                </div>
                                <div className="space-y-1.5 pt-4 border-t px-2">
                                    <Label className="text-[10px] uppercase font-bold text-muted-foreground tracking-wider">Technical Identifier</Label>
                                    <div className="flex items-center gap-2">
                                        <code className="flex-1 text-[10px] bg-muted/50 p-1.5 rounded-md font-mono border truncate">
                                            {selectedTemplate.key}
                                        </code>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>

                {/* Preview Dialog */}
                <Dialog open={isPreviewOpen} onOpenChange={setIsPreviewOpen}>
                    <DialogContent className="max-w-4xl max-h-[90vh] overflow-hidden flex flex-col p-0">
                        <div className="p-6 border-b bg-muted/30">
                            <DialogHeader>
                                <DialogTitle className="flex items-center gap-2">
                                    <Eye className="h-5 w-5 text-primary" /> Live Email Preview
                                </DialogTitle>
                                <DialogDescription>This shows how your email will look in most clients.</DialogDescription>
                            </DialogHeader>
                        </div>
                        <Tabs defaultValue="rendered" className="flex-1 overflow-hidden flex flex-col p-6 pt-2">
                            <TabsList className="grid w-64 grid-cols-2 self-end mb-4">
                                <TabsTrigger value="rendered">Visual</TabsTrigger>
                                <TabsTrigger value="source">Code</TabsTrigger>
                            </TabsList>
                            <div className="flex-1 overflow-hidden rounded-xl border bg-background shadow-inner">
                                <TabsContent value="rendered" className="h-full overflow-auto bg-white p-0 m-0">
                                    <div className="bg-neutral-50 border-b p-4 sticky top-0 z-10">
                                        <div className="grid grid-cols-[80px_1fr] gap-x-4 gap-y-1">
                                            <span className="text-[10px] font-bold text-neutral-400 uppercase text-right">To:</span>
                                            <span className="text-xs font-medium">john@example.com (John Doe)</span>
                                            <span className="text-[10px] font-bold text-neutral-400 uppercase text-right">Subject:</span>
                                            <span className="text-xs font-semibold text-primary">{previewContent?.subject}</span>
                                        </div>
                                    </div>
                                    <div className="p-8 pb-12" dangerouslySetInnerHTML={{ __html: previewContent?.html }} />
                                </TabsContent>
                                <TabsContent value="source" className="h-full m-0 p-0">
                                    <Textarea readOnly className="h-full font-mono text-xs border-0 focus-visible:ring-0 rounded-none bg-neutral-900 text-neutral-100" value={previewContent?.html} />
                                </TabsContent>
                            </div>
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
                            <p className="text-xs text-muted-foreground bg-muted/50 p-3 rounded-lg flex gap-2">
                                <Info className="h-4 w-4 text-primary shrink-0" />
                                This will send a live email using your current SMTP settings. Make sure your Mail config is correct.
                            </p>
                        </div>
                        <DialogFooter>
                            <Button variant="outline" onClick={() => setIsTestSendOpen(false)}>Cancel</Button>
                            <Button onClick={() => testSendMutation.mutate(testEmail)} disabled={testSendMutation.isPending || !testEmail}>
                                {testSendMutation.isPending ? "Sending..." : "Send Test Now"}
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
                        <h1 className="text-2xl font-bold tracking-tight">Email Templates</h1>
                        <p className="text-sm text-muted-foreground">Manage automated system notifications and branding.</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <div className="relative w-64 group">
                            <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground group-focus-within:text-primary transition-colors" />
                            <Input
                                placeholder="Search templates..."
                                className="pl-9 bg-muted/40 border-transparent focus:border-primary/20 transition-all"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                            />
                        </div>
                        <Button onClick={() => setIsCreateOpen(true)} className="shadow-sm">
                            <Plus className="mr-2 h-4 w-4" /> Create New
                        </Button>
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {filteredTemplates.map((template) => (
                        <Card key={template.id} className="group hover:border-primary/40 transition-all hover:shadow-md bg-card/50 backdrop-blur-sm">
                            <CardHeader className="pb-3">
                                <div className="flex items-start justify-between">
                                    <div className="p-2.5 bg-primary/5 rounded-xl text-primary group-hover:bg-primary/10 transition-colors">
                                        <Mail className="h-5 w-5" />
                                    </div>
                                    <Badge variant={template.is_active ? "default" : "secondary"} className="text-[10px] px-2 py-0">
                                        {template.is_active ? "Enabled" : "Disabled"}
                                    </Badge>
                                </div>
                                <CardTitle className="mt-4 text-lg">{template.name}</CardTitle>
                                <CardDescription className="line-clamp-2 mt-1 min-h-[40px]">{template.description || "System notification template"}</CardDescription>
                            </CardHeader>
                            <CardContent className="pb-4 pt-0">
                                <div className="flex items-center gap-2">
                                    <code className="text-[10px] bg-muted px-2 py-0.5 rounded border font-mono text-muted-foreground">
                                        {template.key}
                                    </code>
                                    <Badge variant="outline" className="text-[9px] uppercase tracking-tighter opacity-70">
                                        {template.format}
                                    </Badge>
                                </div>
                            </CardContent>
                            <div className="p-4 border-t bg-muted/5 group-hover:bg-muted/10 transition-colors flex justify-end">
                                <Button size="sm" variant="secondary" className="h-8 text-xs" onClick={() => setSelectedTemplate(template)}>
                                    Configure Template
                                </Button>
                            </div>
                        </Card>
                    ))}
                    {filteredTemplates.length === 0 && (
                        <div className="col-span-full p-20 text-center border rounded-2xl border-dashed bg-muted/10">
                            <div className="bg-muted p-4 rounded-full w-fit mx-auto mb-4 border border-primary/5">
                                <Mail className="h-8 w-8 text-muted-foreground/30" />
                            </div>
                            <h3 className="text-xl font-semibold tracking-tight">No templates found</h3>
                            <p className="text-sm text-muted-foreground max-w-[300px] mx-auto mt-2">
                                Try a different search term or start fresh by creating a new system email.
                            </p>
                            <Button variant="outline" className="mt-6" onClick={() => setIsCreateOpen(true)}>
                                Create First Template
                            </Button>
                        </div>
                    )}
                </div>
            </div>

            {/* Create Template Dialog */}
            <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
                <DialogContent className="max-w-xl p-0 overflow-hidden">
                    <div className="p-6 border-b bg-muted/30">
                        <DialogHeader>
                            <DialogTitle className="text-xl">Create New Email Template</DialogTitle>
                            <DialogDescription>Define a new template. You'll need to hook this key into your backend logic.</DialogDescription>
                        </DialogHeader>
                    </div>
                    <div className="p-6">
                        <div className="grid gap-6 sm:grid-cols-2">
                            <div className="space-y-2">
                                <Label className="text-xs font-bold uppercase tracking-wider">Internal Name</Label>
                                <Input placeholder="e.g. Welcome Email" value={newTemplate.name} onChange={e => setNewTemplate({ ...newTemplate, name: e.target.value })} />
                            </div>
                            <div className="space-y-2">
                                <Label className="text-xs font-bold uppercase tracking-wider">Storage Key</Label>
                                <Input placeholder="e.g. welcome_user" className="font-mono" value={newTemplate.key} onChange={e => setNewTemplate({ ...newTemplate, key: e.target.value })} />
                            </div>
                            <div className="col-span-full space-y-2">
                                <Label className="text-xs font-bold uppercase tracking-wider">Default Subject</Label>
                                <Input placeholder="Hello {{user.name}}, welcome!" value={newTemplate.subject} onChange={e => setNewTemplate({ ...newTemplate, subject: e.target.value })} />
                            </div>
                            <div className="col-span-full space-y-2">
                                <Label className="text-xs font-bold uppercase tracking-wider">Render Format</Label>
                                <Select value={newTemplate.format} onValueChange={(v) => setNewTemplate({ ...newTemplate, format: v })}>
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Select a format" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="markdown">
                                            <div className="flex items-center">
                                                <FileText className="h-4 w-4 mr-2 opacity-70" />
                                                <span>Markdown (Standard)</span>
                                            </div>
                                        </SelectItem>
                                        <SelectItem value="html">
                                            <div className="flex items-center">
                                                <Code2 className="h-4 w-4 mr-2 opacity-70" />
                                                <span>Raw HTML Code</span>
                                            </div>
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                    </div>
                    <div className="p-6 border-t bg-muted/10 flex justify-end gap-3">
                        <Button variant="ghost" onClick={() => setIsCreateOpen(false)}>Cancel</Button>
                        <Button disabled={!newTemplate.key || !newTemplate.name || createMutation.isPending} onClick={() => createMutation.mutate(newTemplate)}>
                            {createMutation.isPending ? "Setting up..." : "Create Template"}
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}
