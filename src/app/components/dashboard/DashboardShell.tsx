import { useState, type ReactNode } from "react";
import { type LucideIcon, Menu, X, Bell, Search, LogOut } from "lucide-react";
import { Button } from "../ui/button";
import { Input } from "../ui/input";
import { Avatar, AvatarFallback } from "../ui/avatar";
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from "../ui/dropdown-menu";
import { cn } from "../ui/utils";

interface DashboardTab {
  id: string;
  label: string;
  icon: LucideIcon;
}

interface DashboardShellProps {
  title: string;
  subtitle: string;
  tabs: DashboardTab[];
  activeTab: string;
  onTabChange: (id: string) => void;
  userName: string;
  userRole: string;
  showSearch?: boolean;
  searchPlaceholder?: string;
  onLogout: () => void;
  children: ReactNode;
}

export default function DashboardShell({
  title,
  subtitle,
  tabs,
  activeTab,
  onTabChange,
  userName,
  userRole,
  showSearch = false,
  searchPlaceholder = "Search...",
  onLogout,
  children,
}: DashboardShellProps) {
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [notificationsOpen, setNotificationsOpen] = useState(false);

  return (
    <div className="min-h-screen bg-slate-100 flex">
      <aside
        className={cn(
          "fixed inset-y-0 left-0 z-50 w-72 bg-gradient-to-b from-blue-900 via-sky-900 to-cyan-900 text-white shadow-xl transition-transform duration-300 ease-in-out",
          sidebarOpen ? "translate-x-0" : "-translate-x-full lg:translate-x-0",
        )}
      >
        <div className="h-full flex flex-col">
          <div className="px-6 py-4 border-b border-white/10 flex items-center justify-between gap-3">
            <div className="flex items-center gap-3">
              <div className="rounded-2xl bg-white/95 p-3 shadow-sm ring-1 ring-slate-200">
                <img src="/logo.png" alt="HarborAI Logo" className="w-12 h-12 sm:w-14 sm:h-14 object-contain" />
              </div>
              <div>
                <p className="text-xs uppercase tracking-[0.24em] text-cyan-200/90">HarborAI</p>
                <p className="font-semibold text-lg text-white">Dashboard</p>
              </div>
            </div>
            <button type="button" className="lg:hidden" onClick={() => setSidebarOpen(false)}>
              <X className="w-6 h-6 text-slate-100" />
            </button>
          </div>

          <nav className="flex-1 overflow-y-auto px-4 py-6 space-y-2">
            {tabs.map((tab) => {
              const Icon = tab.icon;
              const active = activeTab === tab.id;
              return (
                <button
                  key={tab.id}
                  type="button"
                  onClick={() => {
                    onTabChange(tab.id);
                    setSidebarOpen(false);
                  }}
                  className={cn(
                    "w-full flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium transition-all duration-300",
                    active
                      ? "bg-white/10 text-white shadow-lg"
                      : "text-slate-200 hover:bg-white/10 hover:text-white",
                  )}
                >
                  <Icon className="w-5 h-5" />
                  <span>{tab.label}</span>
                </button>
              );
            })}
          </nav>

          <div className="px-6 py-4 border-t border-white/10">
            <Button
              type="button"
              variant="ghost"
              className="w-full justify-start text-white hover:bg-white/10"
              onClick={onLogout}
            >
              <LogOut className="w-5 h-5" />
              Logout
            </Button>
          </div>
        </div>
      </aside>

      {sidebarOpen && (
        <div
          className="fixed inset-0 bg-black/40 z-40 lg:hidden"
          onClick={() => setSidebarOpen(false)}
        />
      )}

      <div className="flex-1 flex flex-col min-w-0 lg:ml-72 h-screen overflow-hidden">
        <header className="bg-gradient-to-r from-blue-600 via-cyan-600 to-sky-600 text-white shadow-lg sticky top-0 z-30">
          <div className="px-6 py-4 flex items-center justify-between gap-4">
            <div className="flex items-center gap-4 flex-1 min-w-0">
              <button
                type="button"
                className="lg:hidden rounded-2xl bg-white/10 p-2 text-white transition hover:bg-white/20"
                onClick={() => setSidebarOpen(true)}
              >
                <Menu className="w-6 h-6" />
              </button>
              <div className="min-w-0">
                <p className="text-sm uppercase tracking-[0.24em] text-cyan-100/90">{subtitle}</p>
                <h1 className="text-2xl font-semibold text-white truncate">{title}</h1>
              </div>
            </div>

            <div className="flex items-center gap-3">
              <DropdownMenu open={notificationsOpen} onOpenChange={setNotificationsOpen}>
                <DropdownMenuTrigger asChild>
                  <Button variant="secondary" className="bg-white/10 text-white border-white/20 hover:bg-white/20">
                    <Bell className="w-4 h-4" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-80">
                  <DropdownMenuItem className="font-semibold">Notifications</DropdownMenuItem>
                  <DropdownMenuItem>3 new order requests</DropdownMenuItem>
                  <DropdownMenuItem>2 program updates</DropdownMenuItem>
                  <DropdownMenuItem>System maintenance scheduled</DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
              <div className="flex items-center gap-3 rounded-full bg-white/10 px-3 py-2 border border-white/15">
                <Avatar className="w-9 h-9 ring-1 ring-white/20">
                  <AvatarFallback className="bg-cyan-100 text-cyan-700">{userName.charAt(0).toUpperCase()}</AvatarFallback>
                </Avatar>
                <div className="hidden md:block">
                  <p className="text-sm font-medium text-white">{userName}</p>
                  <p className="text-xs text-cyan-200/90">{userRole}</p>
                </div>
              </div>
            </div>
          </div>
          {showSearch && (
            <div className="px-6 pb-4">
              <div className="relative max-w-xl">
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-200" />
                <Input
                  placeholder={searchPlaceholder}
                  className="w-full rounded-2xl border border-white/20 bg-white/95 pl-10 text-slate-900 shadow-sm"
                />
              </div>
            </div>
          )}
        </header>

        <main className="flex-1 overflow-auto p-6">{children}</main>
      </div>
    </div>
  );
}
