import { SidebarTrigger } from "@/components/ui/sidebar";
import { useAuth } from "@/contexts/AuthContext";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { LogOut, User, Settings, Shield } from "lucide-react";
import { Link } from "react-router-dom";
import { NotificationCenter } from "@/components/NotificationCenter";
import { LanguageSwitcher } from "@/components/LanguageSwitcher";
import { CurrencySwitcher } from "@/components/CurrencySwitcher";
import { useCurrency } from "@/contexts/CurrencyContext";
import { Wallet } from "lucide-react";

export function Topbar() {
  const { user, logout } = useAuth();
  const { format } = useCurrency();
  const settingsPath = user?.role === "admin" ? "/admin" : "/app/settings";

  return (
    <header className="flex h-14 items-center justify-between border-b px-4">
      <div className="flex items-center gap-2">
        <SidebarTrigger />
        <span className="text-sm font-semibold text-primary">UpgradedProxy</span>
        {user?.role === "admin" && (
          <Badge variant="secondary" className="bg-primary/10 text-primary border-primary/20 gap-1 ml-2">
            <Shield className="h-3 w-3" /> Admin Mode
          </Badge>
        )}
      </div>
      <div className="flex items-center gap-2">
        {user?.role !== "admin" && (
          <Button variant="ghost" size="sm" className="gap-2 text-xs font-semibold hover:bg-primary/5 transition-colors hidden md:flex" asChild>
            <Link to="/app/billing">
              <Wallet className="h-4 w-4 text-primary" />
              <span>{format(user?.balance ?? 0)}</span>
            </Link>
          </Button>
        )}
        <CurrencySwitcher />
        <LanguageSwitcher />
        <NotificationCenter />
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="ghost" size="sm" className="gap-2">
              <User className="h-4 w-4" />
              <span className="hidden text-sm sm:inline">{user?.email}</span>
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end" className="w-48">
            <DropdownMenuLabel className="font-normal">
              <p className="text-sm font-medium">{user?.name}</p>
              <p className="text-xs text-muted-foreground">{user?.email}</p>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem asChild>
              <Link to={settingsPath} className="flex items-center gap-2">
                <Settings className="h-4 w-4" />
                Settings
              </Link>
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem onClick={() => logout()} className="flex items-center gap-2 text-destructive focus:text-destructive">
              <LogOut className="h-4 w-4" />
              Log out
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </div>
    </header>
  );
}
