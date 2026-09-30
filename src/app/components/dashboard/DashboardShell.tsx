import { useEffect, useRef, useState, type ReactNode } from "react";
import { Link } from "react-router-dom";
import { type LucideIcon, Menu, X, Bell, CircleHelp, ChevronRight, LogOut, MoreHorizontal } from "lucide-react";
import { Button } from "../ui/button";
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
  onLogout,
  children,
}: DashboardShellProps) {
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [notificationsOpen, setNotificationsOpen] = useState(false);
  const desktopNavRef = useRef<HTMLElement>(null);
  const [hasMoreTabs, setHasMoreTabs] = useState(false);
  const hasMobileDock = userRole !== "DA/LGU Personnel";
  const primaryMobileIds = userRole === "Producer"
    ? ["dashboard", "listings", "orders", "insights"]
    : ["overview", "browse", "orders", "trends"];
  const mobileTabs = primaryMobileIds.map((id) => tabs.find((tab) => tab.id === id)).filter((tab): tab is DashboardTab => Boolean(tab));
  const mobileMoreTabs = tabs.filter((tab) => !primaryMobileIds.includes(tab.id));

  useEffect(() => {
    const nav = desktopNavRef.current;
    if (!nav) return;

    const updateScrollIndicator = () => {
      setHasMoreTabs(nav.scrollWidth - nav.clientWidth - nav.scrollLeft > 1);
    };

    updateScrollIndicator();
    nav.addEventListener("scroll", updateScrollIndicator, { passive: true });
    window.addEventListener("resize", updateScrollIndicator);

    return () => {
      nav.removeEventListener("scroll", updateScrollIndicator);
      window.removeEventListener("resize", updateScrollIndicator);
    };
  }, [tabs]);

  const scrollToMoreTabs = () => {
    desktopNavRef.current?.scrollBy({ left: 220, behavior: "smooth" });
  };

  return (
    <div className="relative min-h-screen overflow-x-hidden bg-[#F5F1E5] font-body text-[#123C5C]">
      <style>{`
        @import url('https://fonts.googleapis.com/css2?family=Anton&family=Baloo+2:wght@400;500;600;700;800&display=swap');
        .font-display { font-family: 'Anton', ui-sans-serif, sans-serif; }
        .font-body { font-family: 'Baloo 2', ui-rounded, system-ui, sans-serif; }
        .dashboard-nav-scrollbar { scrollbar-width: none; }
        .dashboard-nav-scrollbar::-webkit-scrollbar { display: none; }
      `}</style>

      <div className="pointer-events-none fixed -left-32 top-36 h-72 w-72 rounded-full bg-[#22C55E]/15 blur-[100px]" />
      <div className="pointer-events-none fixed -right-32 top-1/3 h-80 w-80 rounded-full bg-[#22D3EE]/15 blur-[110px]" />

      <header className="sticky top-0 z-30 border-b border-[#E7E1D0] bg-[#F5F1E5]/95 shadow-sm backdrop-blur-sm">
        <div className="h-1 bg-gradient-to-r from-[#22C55E] via-[#0F9488] to-[#22D3EE]" />
        <div className="container mx-auto flex min-h-16 items-center gap-4 px-4 py-3 lg:px-6">
          <Link to="/" className="flex shrink-0 items-center gap-2 text-[#123C5C] sm:gap-3 lg:w-[190px]">
            <img src="/logo.png" alt="HarborAI Logo" className="h-10 w-10 object-contain sm:h-11 sm:w-11" />
            <div className="hidden sm:block">
              <p className="font-display text-xl tracking-tight">HarborAI</p>
              <p className="text-[10px] font-bold uppercase tracking-[0.16em] text-[#0F9488]">Dashboard</p>
            </div>
          </Link>

          <div className="relative hidden min-w-0 flex-1 lg:block">
            <nav ref={desktopNavRef} className="dashboard-nav-scrollbar min-w-0 overflow-x-auto overscroll-x-contain pr-8" aria-label="Dashboard navigation">
              <div className="flex min-w-max items-center gap-2 px-2">
                {tabs.map((tab) => {
                  const active = activeTab === tab.id;
                  const Icon = tab.icon;
                  return (
                    <button
                      key={tab.id}
                      type="button"
                      onClick={() => onTabChange(tab.id)}
                      className={cn(
                        "relative shrink-0 whitespace-nowrap rounded-full px-3 py-2 text-sm font-bold transition-all duration-200",
                        active
                          ? "bg-[#0F9488] text-white shadow-md shadow-[#0F9488]/20"
                          : "text-[#0F9488] hover:bg-[#22C55E]/15 hover:text-[#15803D]",
                      )}
                    >
                      <Icon className="mr-2 inline-block h-4 w-4" aria-hidden="true" />
                      {tab.label}
                    </button>
                  );
                })}
              </div>
            </nav>
            {hasMoreTabs && (
              <button
                type="button"
                onClick={scrollToMoreTabs}
                className="absolute right-0 top-0 flex h-full w-9 items-center justify-center bg-gradient-to-l from-[#F5F1E5] via-[#F5F1E5]/95 to-transparent text-[#0F9488]"
                aria-label="Scroll to more dashboard navigation tabs"
                title="More navigation tabs"
              >
                <ChevronRight className="h-5 w-5" aria-hidden="true" />
              </button>
            )}
          </div>

          <div className="ml-auto flex shrink-0 items-center gap-2">
            <Button
              type="button"
              variant="ghost"
              size="icon"
              className={`rounded-full text-[#123C5C] hover:bg-[#22C55E]/20 hover:text-[#15803D] lg:hidden ${hasMobileDock ? "hidden" : ""}`}
              onClick={() => setSidebarOpen((open) => !open)}
              aria-label="Open dashboard navigation"
              title="Dashboard navigation"
            >
              {sidebarOpen ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
            </Button>
            <Button
              asChild
              variant="ghost"
              size="icon"
              className="rounded-full text-[#123C5C] hover:bg-[#22C55E]/20 hover:text-[#15803D]"
            >
              <Link to="/faq" aria-label="Tulong at FAQ" title="Tulong at FAQ">
                <CircleHelp className="h-5 w-5" />
              </Link>
            </Button>
            <DropdownMenu open={notificationsOpen} onOpenChange={setNotificationsOpen}>
              <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" className="rounded-full text-[#123C5C] hover:bg-[#22C55E]/20 hover:text-[#15803D]" aria-label="Notifications">
                  <Bell className="h-5 w-5" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" className="w-80">
                <DropdownMenuItem className="font-bold text-[#123C5C]">Notifications</DropdownMenuItem>
                <DropdownMenuItem>3 bagong order requests</DropdownMenuItem>
                <DropdownMenuItem>2 program updates</DropdownMenuItem>
                <DropdownMenuItem>Naka-schedule ang system maintenance</DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button
                  variant="ghost"
                  className="h-auto rounded-full px-2 py-1.5 text-left hover:bg-[#22C55E]/15 sm:px-3"
                  aria-label={`Open profile menu for ${userName}`}
                >
                  <Avatar className="h-9 w-9 ring-2 ring-[#22C55E]/50">
                    <AvatarFallback className="bg-[#FDE68A] font-bold text-[#123C5C]">{userName.charAt(0).toUpperCase()}</AvatarFallback>
                  </Avatar>
                  <span className="hidden max-w-32 md:block">
                    <span className="block truncate text-sm font-bold text-[#123C5C]">{userName}</span>
                    <span className="block truncate text-xs text-[#45586B]">{userRole}</span>
                  </span>
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" sideOffset={8} className="w-60">
                <DropdownMenuItem className="font-bold text-[#123C5C]">{userName}</DropdownMenuItem>
                <DropdownMenuItem className="text-xs text-[#45586B]">{userRole}</DropdownMenuItem>
                <DropdownMenuItem
                  onClick={onLogout}
                  className="mt-1 border-t border-[#E7E1D0] pt-2 font-bold text-[#B42318] focus:bg-[#FEE4E2] focus:text-[#B42318]"
                >
                  <LogOut className="mr-2 h-4 w-4" />
                  Logout
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        </div>

        {sidebarOpen && (
          <div className="border-t border-[#E7E1D0] bg-white px-4 py-3 shadow-sm lg:hidden">
            <nav className="container mx-auto grid gap-1 sm:grid-cols-2" aria-label="Mobile dashboard navigation">
              {tabs.map((tab) => {
                const active = activeTab === tab.id;
                const Icon = tab.icon;
                return (
                  <button
                    key={tab.id}
                    type="button"
                    onClick={() => {
                      onTabChange(tab.id);
                      setSidebarOpen(false);
                    }}
                    className={cn(
                      "flex items-center rounded-xl px-3 py-3 text-left text-sm font-bold transition-colors",
                      active ? "bg-gradient-to-r from-[#0F9488] to-[#22C55E] text-white shadow-md" : "text-[#0F9488] hover:bg-[#22C55E]/15 hover:text-[#15803D]",
                    )}
                  >
                    <Icon className="mr-2 h-4 w-4 shrink-0" aria-hidden="true" />
                    {tab.label}
                  </button>
                );
              })}
              <Link to="/faq" onClick={() => setSidebarOpen(false)} className="flex items-center rounded-xl px-3 py-3 text-sm font-bold text-[#0F9488] hover:bg-[#22C55E]/15 hover:text-[#15803D] sm:hidden">
                Tulong at FAQ
              </Link>
            </nav>
          </div>
        )}

      </header>

      <main className={`relative z-10 min-h-[calc(100vh-4rem)] w-full p-3 font-body sm:p-6 lg:p-8 ${hasMobileDock ? "pb-24 sm:pb-24 lg:pb-8" : ""}`}>{children}</main>
      {hasMobileDock && <nav aria-label="Mobile dashboard navigation" className="fixed inset-x-0 bottom-0 z-40 border-t border-[#E7E1D0] bg-white/95 px-2 pt-2 shadow-[0_-6px_24px_rgba(18,60,92,0.12)] backdrop-blur lg:hidden" style={{ paddingBottom: "max(env(safe-area-inset-bottom), 0.5rem)" }}>
        <div className="mx-auto flex max-w-xl items-stretch justify-around">
          {mobileTabs.map((tab) => { const Icon = tab.icon; const selected = activeTab === tab.id; return <button key={tab.id} type="button" onClick={() => onTabChange(tab.id)} aria-label={tab.label} aria-current={selected ? "page" : undefined} className={`flex min-h-11 min-w-0 flex-1 flex-col items-center justify-center gap-0.5 rounded-xl px-1 text-[10px] font-bold ${selected ? "text-[#0F9488]" : "text-[#617184]"}`}><Icon className="h-5 w-5" aria-hidden="true"/><span className="max-w-full truncate">{tab.label}</span></button>; })}
          <DropdownMenu>
            <DropdownMenuTrigger asChild><button type="button" aria-label="More dashboard sections" className={`flex min-h-11 min-w-0 flex-1 flex-col items-center justify-center gap-0.5 rounded-xl px-1 text-[10px] font-bold ${mobileMoreTabs.some((tab) => tab.id === activeTab) ? "text-[#0F9488]" : "text-[#617184]"}`}><MoreHorizontal className="h-5 w-5"/><span>More</span></button></DropdownMenuTrigger>
            <DropdownMenuContent align="end" side="top" className="mb-2 w-56">{mobileMoreTabs.map((tab) => { const Icon = tab.icon; return <DropdownMenuItem key={tab.id} onClick={() => onTabChange(tab.id)} className="min-h-11"><Icon className="mr-2 h-4 w-4"/>{tab.label}</DropdownMenuItem>; })}</DropdownMenuContent>
          </DropdownMenu>
        </div>
      </nav>}
    </div>
  );
}
