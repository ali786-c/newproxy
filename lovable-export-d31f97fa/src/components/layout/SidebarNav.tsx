import { useState } from "react";
import {
  Sidebar,
  SidebarContent,
  SidebarGroup,
  SidebarGroupLabel,
  SidebarGroupContent,
  SidebarMenu,
  SidebarMenuItem,
  SidebarMenuButton,
} from "@/components/ui/sidebar";
import { NavLink } from "@/components/NavLink";
import { useAuth } from "@/contexts/AuthContext";
import { useI18n } from "@/contexts/I18nContext";
import { ChevronDown } from "lucide-react";
import {
  LayoutDashboard,
  Globe,
  BarChart3,
  Settings,
  CreditCard,
  Users,
  Package,
  Shield,
  FileText,
  Mail,
  Bell,
  Home,
  Wifi,
  Server,
  Smartphone,
  Code,
  MonitorSmartphone,
  Gift,
  Receipt,
  BookOpen,
  Activity,
  HelpCircle,
  Wallet,
  Gauge,
  Layers,
  Building2,
  Lock,
  ShieldAlert,
  DollarSign,
  Download,
  Store,
} from "lucide-react";

interface NavItem {
  title: string;
  url: string;
  icon: typeof Globe;
  badge?: string;
}

interface NavSection {
  label: string;
  items: NavItem[];
  collapsible?: boolean;
  defaultOpen?: boolean;
}

const CLIENT_SECTIONS: NavSection[] = [
  {
    label: "",
    items: [
      { title: "Overview", url: "/app", icon: LayoutDashboard },
    ],
  },
  {
    label: "Proxy Management",
    collapsible: true,
    defaultOpen: true,
    items: [
      { title: "Generate Proxies", url: "/app/proxies/generate", icon: Package, badge: "Action" },
      { title: "Residential", url: "/app/proxies/residential", icon: Globe },
      { title: "Datacenter", url: "/app/proxies/datacenter", icon: Server },
      { title: "Mobile", url: "/app/proxies/mobile", icon: Smartphone },
      { title: "Datacenter IPv6", url: "/app/proxies/datacenter-ipv6", icon: Server },
      { title: "Datacenter (Unmetered)", url: "/app/proxies/datacenter-unmetered", icon: Server },
    ],
  },
  /* {
    label: "Scraping Solutions",
    collapsible: true,
    defaultOpen: false,
    items: [
      { title: "Scraper API", url: "/app/scraper-api", icon: Code, badge: "Beta" },
      { title: "Scraping Browser", url: "/app/scraping-browser", icon: MonitorSmartphone, badge: "Beta" },
    ],
  }, */
  {
    label: "Earn",
    items: [
      { title: "Referral Program", url: "/app/referral", icon: Gift },
    ],
  },
  {
    label: "Account",
    items: [
      { title: "Account & Billing", url: "/app/billing", icon: CreditCard },
      { title: "Invoices", url: "/app/invoices", icon: Receipt },
      { title: "Security & 2FA", url: "/app/security", icon: Shield },
      { title: "Organization", url: "/app/organization", icon: Building2 },
      // { title: "Custom Domains", url: "/app/custom-domains", icon: Globe }, // Hidden for now
    ],
  },
  {
    label: "Support",
    items: [
      { title: "Support Tickets", url: "/app/support", icon: HelpCircle },
    ],
  },
  {
    label: "Tools",
    items: [
      { title: "Documentation", url: "/docs", icon: BookOpen },
      { title: "Network Status", url: "/status", icon: Activity },
      { title: "FAQ", url: "/app/faq", icon: HelpCircle },
      { title: "Install App", url: "/install", icon: Download },
    ],
  },
];

const ADMIN_SECTIONS: NavSection[] = [
  {
    label: "",
    items: [
      { title: "Overview", url: "/admin", icon: LayoutDashboard },
    ],
  },
  {
    label: "Management",
    items: [
      { title: "Users", url: "/admin/users", icon: Users },
      { title: "Products", url: "/admin/products", icon: Package },
      { title: "Invoices", url: "/admin/invoices", icon: Receipt },
      { title: "Referrals", url: "/admin/referrals", icon: Gift },
    ],
  },
  {
    label: "Content",
    items: [
      { title: "Blog / Auto-Post", url: "/admin/blog", icon: FileText },
    ],
  },
  {
    label: "Payments",
    items: [
      { title: "Payment Gateways", url: "/admin/payment-gateways", icon: Wallet },
      { title: "Top-Up Settings", url: "/admin/topup-settings", icon: Gauge },
      { title: "Manual Payments", url: "/admin/manual-payments", icon: DollarSign },
      { title: "Coupons", url: "/admin/coupons", icon: Gift },
    ],
  },
  {
    label: "Support",
    items: [
      { title: "Support Tickets", url: "/admin/support", icon: HelpCircle },
    ],
  },
  {
    label: "Resellers",
    items: [
      { title: "White-Label Portal", url: "/admin/resellers", icon: Store },
    ],
  },
  {
    label: "Security & Monitoring",
    items: [
      { title: "Fraud Detection", url: "/admin/fraud-detection", icon: ShieldAlert },
      { title: "SLA Monitoring", url: "/admin/sla-monitoring", icon: Activity },
    ],
  },
  {
    label: "System",
    items: [
      { title: "Audit Log", url: "/admin/audit", icon: Shield },
      { title: "Permissions", url: "/admin/permissions", icon: Lock },
      { title: "Currencies", url: "/admin/currencies", icon: DollarSign },
      { title: "Alerts", url: "/admin/alerts", icon: Bell },
      { title: "Settings", url: "/admin/settings", icon: Settings },
      { title: "Security", url: "/admin/security", icon: Shield },
    ],
  },
];

function CollapsibleSection({ section }: { section: NavSection }) {
  const [open, setOpen] = useState(section.defaultOpen ?? true);

  return (
    <SidebarGroup>
      {section.label && (
        <button
          onClick={() => section.collapsible && setOpen(!open)}
          className="flex w-full items-center justify-between px-2 py-1 text-xs font-semibold uppercase tracking-wider text-muted-foreground hover:text-foreground transition-colors"
        >
          {section.label}
          {section.collapsible && (
            <ChevronDown className={`h-3 w-3 transition-transform ${open ? "" : "-rotate-90"}`} />
          )}
        </button>
      )}
      {(!section.collapsible || open) && (
        <SidebarGroupContent>
          <SidebarMenu>
            {section.items.map((item) => (
              <SidebarMenuItem key={item.url + item.title}>
                <SidebarMenuButton asChild>
                  <NavLink
                    to={item.url}
                    end={item.url === "/app" || item.url === "/admin"}
                    className="flex items-center gap-2 text-sm"
                    activeClassName="bg-sidebar-accent text-sidebar-accent-foreground font-medium"
                  >
                    <item.icon className="h-4 w-4 shrink-0" />
                    <span className="flex-1">{item.title}</span>
                    {item.badge && (
                      <span className={`rounded px-1.5 py-0.5 text-[10px] font-semibold leading-none ${item.badge === "New" ? "bg-primary text-primary-foreground"
                        : item.badge === "Beta" ? "bg-warning text-warning-foreground"
                          : "bg-muted text-muted-foreground"
                        }`}>
                        {item.badge}
                      </span>
                    )}
                  </NavLink>
                </SidebarMenuButton>
              </SidebarMenuItem>
            ))}
          </SidebarMenu>
        </SidebarGroupContent>
      )}
    </SidebarGroup>
  );
}

export function SidebarNav() {
  const { user } = useAuth();
  const { t } = useI18n();
  const isAdmin = user?.role === "admin";

  const CLIENT_SECTIONS: NavSection[] = [
    {
      label: "",
      items: [
        { title: t("nav.overview"), url: "/app", icon: LayoutDashboard },
      ],
    },
    {
      label: "Proxy Management",
      collapsible: true,
      defaultOpen: true,
      items: [
        { title: "Generate Proxies", url: "/app/proxies/generate", icon: Package, badge: "Action" },
        { title: "Residential", url: "/app/proxies/residential", icon: Globe },
        { title: t("nav.products.datacenter") || "Datacenter", url: "/app/proxies/datacenter", icon: Server },
        { title: t("nav.products.mobile") || "Mobile", url: "/app/proxies/mobile", icon: Smartphone },
        { title: "Datacenter IPv6", url: "/app/proxies/datacenter-ipv6", icon: Server },
        { title: "Datacenter (Unmetered)", url: "/app/proxies/datacenter-unmetered", icon: Server },
        { title: t("nav.products.ispShared") || "Shared Static", url: "/app/proxies/isp-shared", icon: Home, badge: "ISP" },
        { title: t("nav.products.ispPrivate") || "Private Static", url: "/app/proxies/isp-private", icon: Home },
        { title: t("nav.products.ispVirgin") || "Virgin Static", url: "/app/proxies/isp-virgin", icon: Home },
      ],
    },
    /* {
      label: "Scraping Solutions",
      collapsible: true,
      defaultOpen: false,
      items: [
        { title: "Scraper API", url: "/app/scraper-api", icon: Code, badge: "Beta" },
        { title: "Scraping Browser", url: "/app/scraping-browser", icon: MonitorSmartphone, badge: "Beta" },
      ],
    }, */
    {
      label: "Earn",
      items: [
        { title: t("nav.referral"), url: "/app/referral", icon: Gift },
      ],
    },
    {
      label: "Account",
      items: [
        { title: t("nav.billing"), url: "/app/billing", icon: CreditCard },
        { title: t("nav.invoices"), url: "/app/invoices", icon: Receipt },
        { title: t("nav.security"), url: "/app/security", icon: Shield },
        { title: t("nav.organization"), url: "/app/organization", icon: Building2 },
        // { title: "Custom Domains", url: "/app/custom-domains", icon: Globe }, // Hidden for now
      ],
    },
    {
      label: t("nav.support"),
      items: [
        { title: t("nav.support"), url: "/app/support", icon: HelpCircle },
      ],
    },
    {
      label: "Tools",
      items: [
        { title: "Documentation", url: "/docs", icon: BookOpen },
        { title: "Network Status", url: "/status", icon: Activity },
        { title: t("nav.faq"), url: "/app/faq", icon: HelpCircle },
        { title: "Install App", url: "/install", icon: Download },
      ],
    },
  ];

  const ADMIN_SECTIONS: NavSection[] = [
    {
      label: "",
      items: [
        { title: t("nav.overview"), url: "/admin", icon: LayoutDashboard },
      ],
    },
    {
      label: "Management",
      items: [
        { title: t("nav.users"), url: "/admin/users", icon: Users },
        { title: t("nav.products"), url: "/admin/products", icon: Package },
        { title: t("nav.invoices"), url: "/admin/invoices", icon: Receipt },
        { title: "Referrals", url: "/admin/referrals", icon: Gift },
      ],
    },
    {
      label: "Content",
      items: [
        { title: "Blog / Auto-Post", url: "/admin/blog", icon: FileText },
        { title: "Email Templates", url: "/admin/email-templates", icon: Mail },
      ],
    },
    {
      label: "Payments",
      items: [
        { title: "Payment Gateways", url: "/admin/payment-gateways", icon: Wallet },
        { title: "Top-Up Settings", url: "/admin/topup-settings", icon: Gauge },
        { title: "Manual Payments", url: "/admin/manual-payments", icon: DollarSign },
        { title: "Coupons", url: "/admin/coupons", icon: Gift },
      ],
    },
    {
      label: t("nav.support"),
      items: [
        { title: t("nav.support"), url: "/admin/support", icon: HelpCircle },
      ],
    },
    {
      label: "Resellers",
      items: [
        { title: "White-Label Portal", url: "/admin/resellers", icon: Store },
      ],
    },
    {
      label: "Security & Monitoring",
      items: [
        { title: "Fraud Detection", url: "/admin/fraud-detection", icon: ShieldAlert },
        { title: "SLA Monitoring", url: "/admin/sla-monitoring", icon: Activity },
        { title: "Fulfillment Monitor", url: "/admin/fulfillment-logs", icon: Activity },
      ],
    },
    {
      label: "System",
      items: [
        { title: t("nav.auditLog"), url: "/admin/audit", icon: Shield },
        { title: t("nav.permissions"), url: "/admin/permissions", icon: Lock },
        { title: "Currencies", url: "/admin/currencies", icon: DollarSign },
        { title: t("nav.alerts"), url: "/admin/alerts", icon: Bell },
        { title: t("nav.settings"), url: "/admin/settings", icon: Settings },
        { title: "Security", url: "/admin/security", icon: Shield },
      ],
    },
  ];

  const sections = isAdmin ? ADMIN_SECTIONS : CLIENT_SECTIONS;

  return (
    <Sidebar>
      <SidebarContent className="py-2">
        {sections.map((section, i) => (
          <CollapsibleSection key={section.label || i} section={section} />
        ))}
      </SidebarContent>
    </Sidebar>
  );
}
