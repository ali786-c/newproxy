import { useState, useEffect } from "react";
import { SEOHead } from "@/components/seo/SEOHead";
import { Card, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Input } from "@/components/ui/input";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from "@/components/ui/table";
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from "@/components/ui/select";
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter,
} from "@/components/ui/dialog";
import {
  Send, Clock, FileText, Search, Plus, Trash2, Edit2, Loader2, Globe, Mail, Settings, Rss, Eye, Sparkles, Save, Bot, Key, Zap, Facebook, Twitter
} from "lucide-react";
import { Switch } from "@/components/ui/switch";
import { toast } from "@/hooks/use-toast";
import {
  useAdminBlogPosts,
  useCreateBlogPost,
  useUpdateBlogPost,
  useDeleteBlogPost,
  usePublishBlogPost,
  useAutoBlogStatus,
  useUpdateAutoBlogSettings,
  useAddAutoBlogKeyword,
  useDeleteAutoBlogKeyword,
  useTriggerAutoBlog,
  useTestTelegram,
  useTestGoogleIndexing,
  useSubmitUrlToIndex,
  useSharePostToTelegram,
  useTestFacebookSharing,
  useSharePostToFacebook,
  useTestXSharing,
  useSharePostToX,
} from "@/hooks/use-backend";

const STATUS_BADGE: Record<string, "default" | "secondary" | "outline"> = {
  draft: "outline",
  published: "default"
};

export default function AdminBlog() {
  const [tab, setTab] = useState("posts");
  const [postFilter, setPostFilter] = useState("all");
  const [search, setSearch] = useState("");

  // Dialog States
  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [isEditOpen, setIsEditOpen] = useState(false);
  const [editingPost, setEditingPost] = useState<any>(null);

  // Form States
  const [title, setTitle] = useState("");
  const [content, setContent] = useState("");
  const [imageUrl, setImageUrl] = useState("");

  // ── API Hooks ──────────────────────────────────
  const { data: rawPosts = [], isLoading: postsLoading } = useAdminBlogPosts();
  const createPost = useCreateBlogPost();
  const updatePost = useUpdateBlogPost();
  const deletePost = useDeleteBlogPost();
  const publishPost = usePublishBlogPost();

  const { data: autoBlogData = { keywords: [], settings: {} }, isLoading: autoLoading } = useAutoBlogStatus();
  const updateAutoSettings = useUpdateAutoBlogSettings();
  const addKeyword = useAddAutoBlogKeyword();
  const deleteKeyword = useDeleteAutoBlogKeyword();
  const triggerAuto = useTriggerAutoBlog();
  const testTelegram = useTestTelegram();
  const testIndexing = useTestGoogleIndexing();
  const submitToIndex = useSubmitUrlToIndex();
  const shareToTelegram = useSharePostToTelegram();
  const testFacebook = useTestFacebookSharing();
  const shareToFacebook = useSharePostToFacebook();
  const testX = useTestXSharing();
  const shareToX = useSharePostToX();

  const [newKeyword, setNewKeyword] = useState("");
  const [newCategory, setNewCategory] = useState("General");

  // Gemini Settings Local State
  const [localApiKey, setLocalApiKey] = useState("");
  const [localModel, setLocalModel] = useState("");

  // Telegram Settings Local State
  const [telegramToken, setTelegramToken] = useState("");
  const [telegramChannel, setTelegramChannel] = useState("");
  const [telegramEnabled, setTelegramEnabled] = useState(false);

  // Google Indexing Local State
  const [googleIndexingEnabled, setGoogleIndexingEnabled] = useState(false);
  const [googleJson, setGoogleJson] = useState("");
  const [googleConfigured, setGoogleConfigured] = useState(false);

  const [facebookConfigured, setFacebookConfigured] = useState(false);
  const [facebookPageId, setFacebookPageId] = useState("");
  const [facebookToken, setFacebookToken] = useState("");
  const [facebookAutoEnabled, setFacebookAutoEnabled] = useState(false);

  // X (Twitter) Settings Local State
  const [xApiKey, setXApiKey] = useState("");
  const [xApiSecret, setXApiSecret] = useState("");
  const [xAccessToken, setXAccessToken] = useState("");
  const [xAccessTokenSecret, setXAccessTokenSecret] = useState("");
  const [xAutoEnabled, setXAutoEnabled] = useState(false);
  const [xSecretConfigured, setXSecretConfigured] = useState(false);
  const [xTokenSecretConfigured, setXTokenSecretConfigured] = useState(false);

  // AI Generation Progress State
  const [progressStep, setProgressStep] = useState(0);
  const steps = [
    "Selecting the best keyword...",
    "Contacting Gemini 2.5 Flash...",
    "Writing high-quality content...",
    "Formatting HTML & SEO optimizations...",
    "Saving to database...",
    "Sharing to Telegram channel...",
    "Posting to Facebook Page...",
    "Tweeting to X (Twitter)..."
  ];

  useEffect(() => {
    let interval: any;
    if (triggerAuto.isPending) {
      setProgressStep(0);
      interval = setInterval(() => {
        setProgressStep(prev => (prev + 1) % steps.length);
      }, 3000);
    } else {
      clearInterval(interval);
    }
    return () => clearInterval(interval);
  }, [triggerAuto.isPending]);

  // Sync settings when data loads
  useEffect(() => {
    if (autoBlogData.settings) {
      setLocalApiKey(autoBlogData.settings.gemini_api_key || "");
      setLocalModel(autoBlogData.settings.gemini_model || "gemini-2.5-flash");
      setTelegramToken(autoBlogData.settings.telegram_bot_token || "");
      setTelegramChannel(autoBlogData.settings.telegram_channel_id || "");
      setTelegramEnabled(autoBlogData.settings.telegram_auto_post_enabled || false);

      setGoogleIndexingEnabled(autoBlogData.settings.google_indexing_enabled || false);
      setGoogleConfigured(autoBlogData.settings.google_indexing_configured || false);

      setFacebookPageId(autoBlogData.settings.facebook_page_id || "");
      setFacebookAutoEnabled(autoBlogData.settings.facebook_auto_post_enabled || false);
      setFacebookConfigured(autoBlogData.settings.facebook_access_token_configured || false);

      setXApiKey(autoBlogData.settings.x_api_key || "");
      setXAccessToken(autoBlogData.settings.x_access_token || "");
      setXAutoEnabled(autoBlogData.settings.x_auto_post_enabled || false);
      setXSecretConfigured(autoBlogData.settings.x_api_secret_configured || false);
      setXTokenSecretConfigured(autoBlogData.settings.x_access_token_secret_configured || false);
    }
  }, [autoBlogData]);

  const posts = rawPosts.map((p: any) => ({
    ...p,
    status: p.is_draft ? "draft" : "published"
  }));

  // ── Actions ─────────────────────────────────────
  const handleCreate = () => {
    createPost.mutate({ title, content, image_url: imageUrl }, {
      onSuccess: () => {
        setIsCreateOpen(false);
        resetForm();
        toast({ title: "Draft created", description: "Your article has been saved as a draft." });
      }
    });
  };

  const handleUpdate = () => {
    updatePost.mutate({ id: editingPost.id, data: { title, content, image_url: imageUrl } }, {
      onSuccess: () => {
        setIsEditOpen(false);
        resetForm();
        toast({ title: "Post updated" });
      }
    });
  };

  const handleTestFacebook = () => {
    testFacebook.mutate(undefined, {
      onSuccess: (data: any) => {
        if (data.ok) {
          toast({ title: "Facebook Connection Success", description: data.description || "Test post shared successfully!" });
        } else {
          toast({ title: "Facebook Test Failed", description: data.description || "Check your Page ID and Token.", variant: "destructive" });
        }
      },
      onError: (err: any) => {
        toast({ title: "Test Error", description: err.message || "Failed to contact API.", variant: "destructive" });
      }
    });
  };

  const handleDelete = (id: number) => {
    if (confirm("Are you sure you want to delete this post?")) {
      deletePost.mutate(id, {
        onSuccess: () => toast({ title: "Post deleted" })
      });
    }
  };

  const handlePublishToggle = (id: number, currentDraft: boolean) => {
    publishPost.mutate({ id, is_draft: !currentDraft }, {
      onSuccess: () => toast({
        title: currentDraft ? "Article Published" : "Moved to Drafts",
        description: currentDraft ? "The article is now live on the blog." : "The article has been hidden."
      })
    });
  };

  const openEdit = (post: any) => {
    setEditingPost(post);
    setTitle(post.title);
    setContent(post.content);
    setImageUrl(post.image_url || "");
    setIsEditOpen(true);
  };

  const resetForm = () => {
    setTitle("");
    setContent("");
    setImageUrl("");
    setEditingPost(null);
  };

  const filteredPosts = posts.filter((p: any) => {
    if (postFilter !== "all" && p.status !== postFilter) return false;
    if (search && !p.title.toLowerCase().includes(search.toLowerCase())) return false;
    return true;
  });

  const draftCount = posts.filter((p: any) => p.status === "draft").length;
  const publishedCount = posts.filter((p: any) => p.status === "published").length;

  const handleSaveAutomation = () => {
    updateAutoSettings.mutate({
      gemini_api_key: localApiKey,
      gemini_model: localModel,
      telegram_auto_post_enabled: telegramEnabled,
      google_indexing_enabled: googleIndexingEnabled,
      google_indexing_json: googleJson,
      facebook_page_id: facebookPageId,
      facebook_access_token: facebookToken,
      facebook_auto_post_enabled: facebookAutoEnabled,
      x_api_key: xApiKey,
      x_api_secret: xApiSecret,
      x_access_token: xAccessToken,
      x_access_token_secret: xAccessTokenSecret,
      x_auto_post_enabled: xAutoEnabled,
    }, {
      onSuccess: () => {
        setGoogleJson(""); // Clear for security
        setFacebookToken(""); // Clear for security
        setXApiSecret(""); // Clear for security
        setXAccessTokenSecret(""); // Clear for security
        toast({
          title: "Settings Saved",
          description: "Automation configuration updated successfully."
        });
      }
    });
  };

  const handleTestTelegram = async () => {
    testTelegram.mutate(undefined, {
      onSuccess: (data: any) => {
        if (data.ok) {
          toast({ title: "Test Success!", description: "Check your Telegram channel." });
        } else {
          toast({ variant: "destructive", title: "Test Failed", description: data.description || "Unknown error" });
        }
      },
      onError: (err: any) => {
        toast({ variant: "destructive", title: "Error", description: err.message || "Could not connect to test API." });
      }
    });
  };

  const handleTestIndexing = async () => {
    testIndexing.mutate(undefined, {
      onSuccess: (data: any) => {
        if (data.ok) {
          toast({ title: "Indexing Verified!", description: data.message });
        } else {
          toast({ variant: "destructive", title: "Indexing Failed", description: data.message || "Unknown error" });
        }
      },
      onError: (err: any) => {
        toast({ variant: "destructive", title: "Error", description: err.message || "Could not connect to indexing API." });
      }
    });
  };

  return (
    <>
      <SEOHead title="Admin — Blog Management" noindex />

      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold">Blog Management</h1>
            <p className="text-sm text-muted-foreground">Manage articles, news, and updates for your users.</p>
          </div>
          <Button onClick={() => setIsCreateOpen(true)} className="gap-2">
            <Plus className="h-4 w-4" /> New Article
          </Button>
        </div>

        {/* Stats */}
        <div className="grid gap-4 sm:grid-cols-4">
          <Card><CardContent className="pt-6"><div className="flex items-center gap-3"><div className="rounded-lg bg-primary/10 p-2.5"><FileText className="h-5 w-5 text-primary" /></div><div><p className="text-2xl font-bold">{posts.length}</p><p className="text-xs text-muted-foreground">Total Posts</p></div></div></CardContent></Card>
          <Card><CardContent className="pt-6"><div className="flex items-center gap-3"><div className="rounded-lg bg-primary/10 p-2.5"><Clock className="h-5 w-5 text-primary" /></div><div><p className="text-2xl font-bold">{draftCount}</p><p className="text-xs text-muted-foreground">Drafts</p></div></div></CardContent></Card>
          <Card><CardContent className="pt-6"><div className="flex items-center gap-3"><div className="rounded-lg bg-primary/10 p-2.5"><Send className="h-5 w-5 text-primary" /></div><div><p className="text-2xl font-bold">{publishedCount}</p><p className="text-xs text-muted-foreground">Published</p></div></div></CardContent></Card>
          <Card><CardContent className="pt-6"><div className="flex items-center gap-3"><div className="rounded-lg bg-primary/10 p-2.5"><Eye className="h-5 w-5 text-primary" /></div><div><p className="text-2xl font-bold">—</p><p className="text-xs text-muted-foreground">Total Views</p></div></div></CardContent></Card>
        </div>

        <Tabs value={tab} onValueChange={setTab}>
          <TabsList>
            <TabsTrigger value="posts" className="gap-1.5"><FileText className="h-3.5 w-3.5" /> Articles</TabsTrigger>
            <TabsTrigger value="automation" className="gap-1.5"><Bot className="h-3.5 w-3.5" /> Automation</TabsTrigger>
            <TabsTrigger value="channels" className="gap-1.5 disabled" disabled><Settings className="h-3.5 w-3.5" /> Channels (Coming Soon)</TabsTrigger>
          </TabsList>

          <TabsContent value="posts" className="mt-4 space-y-4">
            <div className="flex items-center gap-3">
              <div className="relative flex-1">
                <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <Input placeholder="Search posts…" value={search} onChange={(e) => setSearch(e.target.value)} className="pl-9" />
              </div>
              <Select value={postFilter} onValueChange={setPostFilter}>
                <SelectTrigger className="w-32"><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All</SelectItem>
                  <SelectItem value="draft">Drafts</SelectItem>
                  <SelectItem value="published">Published</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <Card>
              <CardContent className="p-0">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Title</TableHead>
                      <TableHead>Author</TableHead>
                      <TableHead>Status</TableHead>
                      <TableHead>Date</TableHead>
                      <TableHead className="text-right">Actions</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {filteredPosts.map((post: any) => (
                      <TableRow key={post.id} className="group">
                        <TableCell>
                          <div>
                            <p className="font-medium text-sm">{post.title}</p>
                            <p className="text-xs text-muted-foreground truncate max-w-[300px]">{post.slug}</p>
                          </div>
                        </TableCell>
                        <TableCell><span className="text-sm font-medium">{post.author?.name || "System"}</span></TableCell>
                        <TableCell>
                          <Badge variant={STATUS_BADGE[post.status] ?? "outline"} className="text-[10px] uppercase">
                            {post.status}
                          </Badge>
                        </TableCell>
                        <TableCell className="text-sm text-muted-foreground">
                          {new Date(post.published_at || post.created_at).toLocaleDateString()}
                        </TableCell>
                        <TableCell>
                          <div className="flex justify-end gap-1">
                            <Button
                              size="icon"
                              variant="ghost"
                              onClick={() => {
                                submitToIndex.mutate(post.id, {
                                  onSuccess: (data: any) => toast({
                                    title: "SEO Indexing",
                                    description: data.message
                                  }),
                                  onError: (error: any) => toast({
                                    variant: "destructive",
                                    title: "Error",
                                    description: error?.message || "Failed to submit for indexing"
                                  })
                                });
                              }}
                              disabled={submitToIndex.isPending || post.is_draft}
                              className="h-8 w-8 text-blue-500 hover:text-blue-600"
                              title="Index Now"
                            >
                              {submitToIndex.isPending && submitToIndex.variables === post.id ? (
                                <Loader2 className="h-3.5 w-3.5 animate-spin" />
                              ) : (
                                <Zap className="h-3.5 w-3.5" />
                              )}
                            </Button>
                            <Button
                              size="icon"
                              variant="ghost"
                              onClick={() => {
                                shareToTelegram.mutate(post.id, {
                                  onSuccess: (data: any) => toast({
                                    title: "Telegram Share",
                                    description: data.message
                                  }),
                                  onError: (error: any) => toast({
                                    variant: "destructive",
                                    title: "Error",
                                    description: error?.message || "Failed to share to Telegram"
                                  })
                                });
                              }}
                              disabled={shareToTelegram.isPending || post.is_draft}
                              className="h-8 w-8 text-green-500 hover:text-green-600"
                              title="Send to Telegram"
                            >
                              {shareToTelegram.isPending && shareToTelegram.variables === post.id ? (
                                <Loader2 className="h-3.5 w-3.5 animate-spin" />
                              ) : (
                                <Send className="h-3.5 w-3.5" />
                              )}
                            </Button>
                            <Button
                              size="icon"
                              variant="ghost"
                              onClick={() => {
                                shareToFacebook.mutate(post.id, {
                                  onSuccess: (data: any) => toast({
                                    title: "Facebook Share",
                                    description: data.message
                                  }),
                                  onError: (error: any) => toast({
                                    variant: "destructive",
                                    title: "Error",
                                    description: error?.message || "Failed to share to Facebook"
                                  })
                                });
                              }}
                              disabled={shareToFacebook.isPending || post.is_draft}
                              className="h-8 w-8 text-blue-600 hover:text-blue-700"
                              title="Send to Facebook"
                            >
                              {shareToFacebook.isPending && shareToFacebook.variables === post.id ? (
                                <Loader2 className="h-3.5 w-3.5 animate-spin" />
                              ) : (
                                <Facebook className="h-3.5 w-3.5" />
                              )}
                            </Button>
                            <Button
                              size="icon"
                              variant="ghost"
                              onClick={() => {
                                shareToX.mutate(post.id, {
                                  onSuccess: (data: any) => toast({
                                    title: "X (Twitter) Share",
                                    description: data.message
                                  }),
                                  onError: (error: any) => toast({
                                    variant: "destructive",
                                    title: "Error",
                                    description: error?.message || "Failed to share to X"
                                  })
                                });
                              }}
                              disabled={shareToX.isPending || post.is_draft}
                              className="h-8 w-8 text-sky-500 hover:text-sky-600"
                              title="Send to X (Twitter)"
                            >
                              {shareToX.isPending && shareToX.variables === post.id ? (
                                <Loader2 className="h-3.5 w-3.5 animate-spin" />
                              ) : (
                                <Twitter className="h-3.5 w-3.5" />
                              )}
                            </Button>
                            <Button size="icon" variant="ghost" onClick={() => openEdit(post)} className="h-8 w-8">
                              <Edit2 className="h-3.5 w-3.5" />
                            </Button>
                            <Button
                              size="sm"
                              variant={post.is_draft ? "default" : "outline"}
                              onClick={() => handlePublishToggle(post.id, post.is_draft)}
                              disabled={publishPost.isPending}
                              className="h-8 gap-1"
                            >
                              {publishPost.isPending && publishPost.variables?.id === post.id ? (
                                <Loader2 className="h-3 w-3 animate-spin" />
                              ) : post.is_draft ? (
                                <><Globe className="h-3 w-3" /> Publish</>
                              ) : (
                                <><Clock className="h-3 w-3" /> Unpublish</>
                              )}
                            </Button>
                            <Button size="icon" variant="ghost" onClick={() => handleDelete(post.id)} className="h-8 w-8 text-destructive hover:text-destructive">
                              <Trash2 className="h-3.5 w-3.5" />
                            </Button>
                          </div>
                        </TableCell>
                      </TableRow>
                    ))}
                    {filteredPosts.length === 0 && (
                      <TableRow><TableCell colSpan={5} className="h-32 text-center text-muted-foreground">{postsLoading ? <Loader2 className="h-6 w-6 animate-spin mx-auto" /> : "No posts found."}</TableCell></TableRow>
                    )}
                  </TableBody>
                </Table>
              </CardContent>
            </Card>
          </TabsContent>
          <TabsContent value="automation" className="mt-4 space-y-6">
            <div className="grid gap-6 md:grid-cols-2">
              {/* API Configuration */}
              <Card>
                <CardContent className="pt-6 space-y-4">
                  <div className="flex items-center gap-2 font-semibold text-lg">
                    <Key className="h-5 w-5 text-primary" />
                    Core Configuration
                  </div>
                  <Tabs defaultValue="gemini">
                    <TabsList className="grid grid-cols-4 w-full h-auto">
                      <TabsTrigger value="gemini" className="text-xs">Gemini AI</TabsTrigger>
                      <TabsTrigger value="telegram" className="text-xs">Telegram</TabsTrigger>
                      <TabsTrigger value="indexing" className="text-xs">Indexing</TabsTrigger>
                      <TabsTrigger value="facebook" className="text-xs">Facebook</TabsTrigger>
                      <TabsTrigger value="x" className="text-xs">X (Twitter)</TabsTrigger>
                    </TabsList>

                    <TabsContent value="gemini" className="space-y-4 mt-4">
                      <div className="space-y-1.5">
                        <Label>Google Gemini API Key</Label>
                        <Input
                          type="password"
                          placeholder="AIza..."
                          value={localApiKey}
                          onChange={(e) => setLocalApiKey(e.target.value)}
                        />
                        <p className="text-[10px] text-muted-foreground">Get your key from Google AI Studio</p>
                      </div>
                      <div className="space-y-1.5">
                        <Label>Model Selection</Label>
                        <Select value={localModel} onValueChange={setLocalModel}>
                          <SelectTrigger><SelectValue /></SelectTrigger>
                          <SelectContent>
                            <SelectItem value="gemini-2.5-flash">Gemini 2.5 Flash (Fash & Verified)</SelectItem>
                            <SelectItem value="gemini-1.5-flash">Gemini 1.5 Flash (Fash & Cheap)</SelectItem>
                            <SelectItem value="gemini-1.5-pro">Gemini 1.5 Pro (High Quality)</SelectItem>
                          </SelectContent>
                        </Select>
                      </div>
                      <div className="flex items-center justify-between pt-2">
                        <div className="space-y-0.5">
                          <Label>Daily Auto-Posting</Label>
                          <p className="text-xs text-muted-foreground">Automatically post every day at 9 AM</p>
                        </div>
                        <Switch
                          checked={autoBlogData.settings?.auto_posting_enabled}
                          onCheckedChange={(checked) => updateAutoSettings.mutate({ auto_blog_enabled: checked })}
                        />
                      </div>
                    </TabsContent>

                    <TabsContent value="telegram" className="space-y-4 mt-4">
                      <div className="space-y-1.5">
                        <Label>Telegram Bot Token</Label>
                        <Input
                          type="password"
                          placeholder="000000:ABC-DEF..."
                          value={telegramToken}
                          onChange={(e) => setTelegramToken(e.target.value)}
                        />
                        <p className="text-[10px] text-muted-foreground">Get Token from @BotFather</p>
                      </div>
                      <div className="space-y-1.5">
                        <Label>Channel ID / Username</Label>
                        <Input
                          placeholder="@my_channel or -100..."
                          value={telegramChannel}
                          onChange={(e) => setTelegramChannel(e.target.value)}
                        />
                        <p className="text-[10px] text-muted-foreground">Your public channel @username</p>
                      </div>
                      <div className="flex items-center justify-between pt-2">
                        <div className="space-y-0.5">
                          <Label>Enable Telegram Sharing</Label>
                          <p className="text-xs text-muted-foreground">Share new articles to Telegram</p>
                        </div>
                        <Switch
                          checked={telegramEnabled}
                          onCheckedChange={setTelegramEnabled}
                        />
                      </div>
                      <Button variant="outline" size="sm" className="w-full gap-2" onClick={handleTestTelegram}>
                        <Send className="h-3.5 w-3.5" /> Send Test Message
                      </Button>
                    </TabsContent>

                    <TabsContent value="indexing" className="space-y-4 mt-4">
                      <div className="space-y-1.5">
                        <Label>Google Service Account JSON</Label>
                        <Textarea
                          placeholder='{ "type": "service_account", ... }'
                          value={googleJson}
                          onChange={(e) => setGoogleJson(e.target.value)}
                          className="font-mono text-[10px] min-h-[120px]"
                          autoComplete="off"
                        />
                        <p className="text-[10px] text-muted-foreground flex items-center gap-1">
                          <Key className="h-3 w-3" />
                          {googleConfigured ? "JSON Key is stored & encrypted ✅" : "JSON Key not uploaded yet ❌"}
                        </p>
                      </div>
                      <div className="flex items-center justify-between pt-2">
                        <div className="space-y-0.5">
                          <Label>Enable Auto-Indexing</Label>
                          <p className="text-xs text-muted-foreground">Notify Google on new posts</p>
                        </div>
                        <Switch
                          checked={googleIndexingEnabled}
                          onCheckedChange={setGoogleIndexingEnabled}
                        />
                      </div>
                      <Button
                        variant="outline"
                        size="sm"
                        className="w-full gap-2"
                        onClick={handleTestIndexing}
                        disabled={testIndexing.isPending}
                      >
                        {testIndexing.isPending ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Globe className="h-3.5 w-3.5" />}
                        {testIndexing.isPending ? "Connecting to Google..." : "Test Google Indexing"}
                      </Button>
                    </TabsContent>

                    <TabsContent value="facebook" className="space-y-4 pt-4">
                      <div className="flex items-center justify-between mb-2">
                        <div className="space-y-1">
                          <h4 className="text-sm font-medium">Automatic Facebook Sharing</h4>
                          <p className="text-xs text-muted-foreground">Share every new blog post to your Facebook Page automatically.</p>
                        </div>
                        <Switch
                          checked={facebookAutoEnabled}
                          onCheckedChange={setFacebookAutoEnabled}
                        />
                      </div>

                      <div className="grid gap-4">
                        <div className="grid gap-2">
                          <Label htmlFor="fb-page-id">Facebook Page ID</Label>
                          <Input
                            id="fb-page-id"
                            placeholder="e.g. 327892273751355"
                            value={facebookPageId}
                            onChange={(e) => setFacebookPageId(e.target.value)}
                            autoComplete="off"
                          />
                        </div>
                        <div className="grid gap-2">
                          <Label htmlFor="fb-token">Page Access Token</Label>
                          <div className="relative">
                            <Input
                              id="fb-token"
                              type="password"
                              placeholder={facebookConfigured ? "••••••••••••••••" : "Paste your Page Access Token"}
                              value={facebookToken}
                              onChange={(e) => setFacebookToken(e.target.value)}
                              autoComplete="off"
                            />
                            {facebookConfigured && !facebookToken && (
                              <span className="absolute right-3 top-2.5 text-[10px] text-green-500 font-medium">CONFIGURED</span>
                            )}
                          </div>
                          <p className="text-[10px] text-muted-foreground">This token is stored encrypted in our database.</p>
                        </div>

                        <Button
                          variant="outline"
                          onClick={handleTestFacebook}
                          disabled={testFacebook.isPending}
                          className="w-full flex items-center justify-center gap-2"
                        >
                          {testFacebook.isPending ? "Testing..." : <><Facebook className="h-4 w-4" /> Test Facebook Post</>}
                        </Button>
                      </div>
                    </TabsContent>
                    <TabsContent value="x" className="space-y-4 pt-4">
                      <div className="flex items-center justify-between mb-2">
                        <div className="space-y-1">
                          <h4 className="text-sm font-medium">Automatic X (Twitter) Sharing</h4>
                          <p className="text-xs text-muted-foreground">Tweet every new blog post automatically.</p>
                        </div>
                        <Switch
                          checked={xAutoEnabled}
                          onCheckedChange={setXAutoEnabled}
                        />
                      </div>

                      <div className="grid gap-4">
                        <div className="grid gap-2">
                          <Label>API Key (Consumer Key)</Label>
                          <Input
                            placeholder="Your X API Key"
                            value={xApiKey}
                            onChange={(e) => setXApiKey(e.target.value)}
                            autoComplete="off"
                          />
                        </div>
                        <div className="grid gap-2">
                          <Label>API Secret Key</Label>
                          <div className="relative">
                            <Input
                              type="password"
                              placeholder={xSecretConfigured ? "••••••••••••••••" : "Paste your API Secret"}
                              value={xApiSecret}
                              onChange={(e) => setXApiSecret(e.target.value)}
                              autoComplete="off"
                            />
                            {xSecretConfigured && !xApiSecret && (
                              <span className="absolute right-3 top-2.5 text-[10px] text-green-500 font-medium">CONFIGURED</span>
                            )}
                          </div>
                        </div>
                        <div className="grid gap-2">
                          <Label>Access Token</Label>
                          <Input
                            placeholder="Your X Access Token"
                            value={xAccessToken}
                            onChange={(e) => setXAccessToken(e.target.value)}
                            autoComplete="off"
                          />
                        </div>
                        <div className="grid gap-2">
                          <Label>Access Token Secret</Label>
                          <div className="relative">
                            <Input
                              type="password"
                              placeholder={xTokenSecretConfigured ? "••••••••••••••••" : "Paste your Token Secret"}
                              value={xAccessTokenSecret}
                              onChange={(e) => setXAccessTokenSecret(e.target.value)}
                              autoComplete="off"
                            />
                            {xTokenSecretConfigured && !xAccessTokenSecret && (
                              <span className="absolute right-3 top-2.5 text-[10px] text-green-500 font-medium">CONFIGURED</span>
                            )}
                          </div>
                        </div>

                        <Button
                          variant="outline"
                          onClick={() => {
                            testX.mutate(undefined, {
                              onSuccess: (data: any) => {
                                if (data.ok) toast({ title: "X Test Success", description: "Test tweet sent!" });
                                else toast({ variant: "destructive", title: "X Test Failed", description: data.description });
                              }
                            });
                          }}
                          disabled={testX.isPending}
                          className="w-full gap-2"
                        >
                          {testX.isPending ? "Testing..." : <><Twitter className="h-4 w-4" /> Test X Tweet</>}
                        </Button>
                      </div>
                    </TabsContent>
                  </Tabs>

                  <div className="pt-2">
                    <Button
                      size="sm"
                      className="w-full gap-2"
                      onClick={handleSaveAutomation}
                      disabled={updateAutoSettings.isPending}
                    >
                      {updateAutoSettings.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
                      Save All Settings
                    </Button>
                  </div>
                </CardContent>
              </Card>

              {/* Manual Trigger */}
              <Card>
                <CardContent className="pt-6 space-y-4">
                  <div className="flex items-center gap-2 font-semibold text-lg">
                    <Zap className="h-5 w-5 text-yellow-500" />
                    Instant Content Generation
                  </div>
                  <p className="text-sm text-muted-foreground italic">
                    Push the button below to immediately generate and publish a professional blog post using your next available keyword.
                  </p>
                  <Button
                    className="w-full h-12 gap-2 text-md font-bold transition-all"
                    variant="secondary"
                    disabled={triggerAuto.isPending || !autoBlogData.settings?.gemini_api_key || !autoBlogData.settings?.gemini_api_key.startsWith('AIza')}
                    onClick={() => {
                      triggerAuto.mutate({}, {
                        onSuccess: (data: any) => toast({ title: "Success!", description: data.message })
                      });
                    }}
                  >
                    {triggerAuto.isPending ? <Loader2 className="h-5 w-5 animate-spin" /> : <Sparkles className="h-5 w-5" />}
                    Generate & Publish Now
                  </Button>

                  {triggerAuto.isPending && (
                    <div className="flex items-center justify-center gap-2 text-primary animate-pulse py-1">
                      <Bot className="h-3 w-3" />
                      <span className="text-[11px] font-medium italic">{steps[progressStep]}</span>
                    </div>
                  )}

                  {(!autoBlogData.settings?.gemini_api_key || !autoBlogData.settings?.gemini_api_key.startsWith('AIza')) && (
                    <p className="text-center text-xs text-destructive">Please configure a valid API key first (starts with AIza).</p>
                  )}
                </CardContent>
              </Card>
            </div>

            {/* Keyword Management */}
            <Card>
              <CardContent className="pt-6">
                <div className="flex items-center justify-between mb-6">
                  <div className="flex items-center gap-2 font-semibold text-lg">
                    <Search className="h-5 w-5 text-primary" />
                    Target Keywords & Topics
                  </div>
                  <div className="flex gap-2">
                    <Input
                      placeholder="e.g. Benefits of Residential Proxies"
                      className="w-64"
                      value={newKeyword}
                      onChange={(e) => setNewKeyword(e.target.value)}
                    />
                    <Button
                      onClick={() => {
                        if (!newKeyword) return;
                        addKeyword.mutate({ keyword: newKeyword, category: newCategory }, {
                          onSuccess: () => setNewKeyword("")
                        });
                      }}
                      disabled={addKeyword.isPending || !newKeyword}
                    >
                      <Plus className="h-4 w-4 mr-2" /> Add
                    </Button>
                  </div>
                </div>

                <div className="rounded-md border">
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Topic / Keyword</TableHead>
                        <TableHead>Category</TableHead>
                        <TableHead>Last Used</TableHead>
                        <TableHead className="text-right">Action</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {autoBlogData.keywords?.map((kw: any) => (
                        <TableRow key={kw.id}>
                          <TableCell className="font-medium">{kw.keyword}</TableCell>
                          <TableCell>
                            <Badge variant="outline" className="text-[10px]">{kw.category || "General"}</Badge>
                          </TableCell>
                          <TableCell className="text-xs text-muted-foreground">
                            {kw.last_used_at ? new Date(kw.last_used_at).toLocaleDateString() : "Never"}
                          </TableCell>
                          <TableCell className="text-right">
                            <Button
                              size="icon"
                              variant="ghost"
                              className="h-8 w-8 text-destructive"
                              onClick={() => deleteKeyword.mutate(kw.id)}
                              disabled={deleteKeyword.isPending}
                            >
                              <Trash2 className="h-3.5 w-3.5" />
                            </Button>
                          </TableCell>
                        </TableRow>
                      ))}
                      {(!autoBlogData.keywords || autoBlogData.keywords.length === 0) && (
                        <TableRow>
                          <TableCell colSpan={4} className="h-24 text-center text-muted-foreground">
                            No keywords added yet. Add some to start auto-blogging.
                          </TableCell>
                        </TableRow>
                      )}
                    </TableBody>
                  </Table>
                </div>
              </CardContent>
            </Card>
          </TabsContent>
        </Tabs>

        {/* Create Dialog */}
        <Dialog open={isCreateOpen} onOpenChange={(open) => { setIsCreateOpen(open); if (!open) resetForm(); }}>
          <DialogContent className="max-w-2xl">
            <DialogHeader>
              <DialogTitle>Create New Article</DialogTitle>
              <DialogDescription>Draft your post. You can publish it once it's saved.</DialogDescription>
            </DialogHeader>
            <div className="space-y-4 py-4">
              <div className="space-y-2">
                <Label>Title</Label>
                <Input value={title} onChange={(e) => setTitle(e.target.value)} placeholder="Article headline..." />
              </div>
              <div className="space-y-2">
                <Label>Hero Image URL</Label>
                <Input value={imageUrl} onChange={(e) => setImageUrl(e.target.value)} placeholder="https://..." />
              </div>
              <div className="space-y-2">
                <Label>Content (HTML/Text)</Label>
                <Textarea value={content} onChange={(e) => setContent(e.target.value)} placeholder="Write your article content here..." className="min-h-[250px]" />
              </div>
            </div>
            <DialogFooter>
              <Button variant="outline" onClick={() => setIsCreateOpen(false)}>Cancel</Button>
              <Button onClick={handleCreate} disabled={createPost.isPending || !title || !content}>
                {createPost.isPending ? <Loader2 className="h-4 w-4 animate-spin mr-2" /> : <Save className="h-4 w-4 mr-2" />}
                Save Draft
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>

        {/* Edit Dialog */}
        <Dialog open={isEditOpen} onOpenChange={(open) => { setIsEditOpen(open); if (!open) resetForm(); }}>
          <DialogContent className="max-w-2xl">
            <DialogHeader>
              <DialogTitle>Edit Article</DialogTitle>
              <DialogDescription>Update the content of your post.</DialogDescription>
            </DialogHeader>
            <div className="space-y-4 py-4">
              <div className="space-y-2">
                <Label>Title</Label>
                <Input value={title} onChange={(e) => setTitle(e.target.value)} placeholder="Article headline..." />
              </div>
              <div className="space-y-2">
                <Label>Hero Image URL</Label>
                <Input value={imageUrl} onChange={(e) => setImageUrl(e.target.value)} placeholder="https://..." />
              </div>
              <div className="space-y-2">
                <Label>Content (HTML/Text)</Label>
                <Textarea value={content} onChange={(e) => setContent(e.target.value)} placeholder="Write your article content here..." className="min-h-[250px]" />
              </div>
            </div>
            <DialogFooter>
              <Button variant="outline" onClick={() => setIsEditOpen(false)}>Cancel</Button>
              <Button onClick={handleUpdate} disabled={updatePost.isPending || !title || !content}>
                {updatePost.isPending ? <Loader2 className="h-4 w-4 animate-spin mr-2" /> : <Save className="h-4 w-4 mr-2" />}
                Update Post
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      </div >
    </>
  );
}
