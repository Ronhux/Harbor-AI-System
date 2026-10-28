import { notificationBody } from '../../contracts/notificationCopy';
import { DashboardText, useDashboardCopy } from '../../i18n/DashboardCopy';
import { LatestRequest } from '../../../lib/LatestRequest';
import { DashboardPortalTheme } from '../../theme/DashboardPortalTheme';
import ThemeSelector from '../../theme/ThemeSelector';
import { useTheme } from '../../theme/ThemeProvider';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '../ui/dialog';
import { notificationTarget } from '../../contracts/notificationNavigation';
import { useCallback, useEffect, useRef, useState, type ReactNode } from "react";
import { Link } from "react-router-dom";
import { type LucideIcon, Menu, X, Bell, CircleHelp, LogOut, MoreHorizontal } from "lucide-react";
import { Button } from "../ui/button";
import { Avatar, AvatarFallback } from "../ui/avatar";
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuTrigger } from "../ui/dropdown-menu";
import { cn } from "../ui/utils";
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from "../ui/accordion";
import { faqCategories } from "../../data/faq";
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetDescription } from "../ui/sheet";
import { request } from "../../../lib/api";
import LanguageSwitcher from "../../i18n/LanguageSwitcher";
import { useLanguage } from "../../i18n/useLanguage";

interface DashboardTab {
  id: string;
  label: string;
  icon: LucideIcon;
}

interface DashboardNavItem extends DashboardTab {
  tabId?: string;
  anchorId?: string;
}

interface DashboardNavSection {
  label: string;
  items: DashboardNavItem[];
}

interface DashboardShellProps {
  title: string;
  subtitle: string;
  tabs: DashboardTab[];
  navSections?: DashboardNavSection[];
  activeTab: string;
  activeNavigationId?: string;
  onTabChange: (id: string) => void;
  userName: string;
  userRole: string;
  workspaceLabel?: string;
  showSearch?: boolean;
  /**
   * Optional override of which tabs appear in the mobile bottom bar.
   * Tabs not listed here are exposed through the "More" menu, so every
   * destination stays reachable without crowding the bar.
   */
  primaryMobileTabIds?: string[];
  searchPlaceholder?: string;
  onLogout: () => void;
  children: ReactNode;
}



export default function DashboardShell({
  title,
  subtitle,
  tabs,
  navSections,
  activeTab,
  activeNavigationId,
  onTabChange,
  userName,
  userRole,
  workspaceLabel = userRole,
  primaryMobileTabIds,
  onLogout,
  children,
}: DashboardShellProps) {
  const copy = useDashboardCopy();
  const { t } = useLanguage();
  const { resolvedTheme } = useTheme();
  const [appearanceOpen, setAppearanceOpen] = useState(false);
  const dashboardRoot = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const el = dashboardRoot.current;
    if (!el) return;
    if (resolvedTheme === 'dark') {
      el.classList.add('dark');
      el.setAttribute('data-harbor-dashboard-theme', 'dark');
    } else {
      el.classList.remove('dark');
      el.setAttribute('data-harbor-dashboard-theme', 'light');
    }
    return () => {
      el.classList.remove('dark');
      el.removeAttribute('data-harbor-dashboard-theme');
    };
  }, [resolvedTheme]);
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const notificationLoads=useRef(new LatestRequest());
  const notificationReads=useRef(new Set<number>());
  const [notificationReadBusy,setNotificationReadBusy]=useState(false);
  const [notificationsOpen, setNotificationsOpen] = useState(false);
  const [notifications, setNotifications] = useState<any[]>([]);
  const [unreadCount, setUnreadCount] = useState(0);
  const [notificationsLoading, setNotificationsLoading] = useState(false);
  const [notificationsError, setNotificationsError] = useState("");
  const hasMobileDock = userRole !== "DA/LGU Personnel" && userRole !== "Super Admin";
  /* A dashboard may declare its own bottom-bar priorities so extra modules
     fall into the "More" menu instead of crowding the bar. Omitted, the
     previous per-role defaults keep applying so other roles are unchanged. */
  const primaryMobileIds = primaryMobileTabIds
    ?? (userRole === "Producer"
      ? ["dashboard", "listings", "orders", "insights"]
      : ["overview", "browse", "orders", "trends"]);
  const mobileTabs = primaryMobileIds.map((id) => tabs.find((tab) => tab.id === id)).filter((tab): tab is DashboardTab => Boolean(tab));
  const mobileMoreTabs = tabs.filter((tab) => !primaryMobileIds.includes(tab.id));
  useEffect(() => {
    const desktop = window.matchMedia('(min-width: 1024px)');
    const closeOnDesktop = () => { if (desktop.matches) setSidebarOpen(false); };
    desktop.addEventListener('change', closeOnDesktop);
    return () => desktop.removeEventListener('change', closeOnDesktop);
  }, []);
  const desktopSections: DashboardNavSection[] = navSections ?? [{ label: t("shell.workspace"), items: tabs }];

  const loadNotifications = useCallback(async (showLoading = false) => {
    const current=notificationLoads.current.begin();
    if (showLoading) setNotificationsLoading(true);
    try {
      const result = await request('/api/notifications');
      if(!current())return;
      const items = result?.data || [];
      setNotifications(items);
      setUnreadCount(Number.isFinite(Number(result?.unread_count)) ? Number(result.unread_count) : items.filter((item: any) => !item.read_at).length);
      setNotificationsError("");
    } catch {
      if(!current())return;
      setNotificationsError("Notifications could not be refreshed. Showing the last loaded results.");
    } finally {
      if (current()) setNotificationsLoading(false);
    }
  }, []);

  useEffect(() => {
    void loadNotifications();
    const timer = window.setInterval(() => void loadNotifications(), 45000);
    return () => {window.clearInterval(timer);notificationLoads.current.invalidate();};
  }, [loadNotifications]);


  return (
    <DashboardPortalTheme.Provider value={resolvedTheme}>
      <div ref={dashboardRoot} className="relative min-h-screen overflow-x-hidden bg-background font-body text-foreground">
      <style>{`
        @import url('https://fonts.googleapis.com/css2?family=Anton&family=Baloo+2:wght@400;500;600;700;800&display=swap');
        .font-display { font-family: 'Anton', ui-sans-serif, sans-serif; }
        .font-body { font-family: 'Baloo 2', ui-rounded, system-ui, sans-serif; }
        .dashboard-nav-scrollbar { scrollbar-width: none; }
        .dashboard-nav-scrollbar::-webkit-scrollbar { display: none; }
      `}</style>

      <Dialog open={appearanceOpen} onOpenChange={setAppearanceOpen}><DialogContent><DialogHeader><DialogTitle>{t("appearance.settings")} → {t("appearance.title")}</DialogTitle><DialogDescription>{t('appearance.description')}</DialogDescription></DialogHeader><ThemeSelector /><p className="text-sm text-muted-foreground">{t('appearance.persistence')}</p></DialogContent></Dialog>
      <div className="pointer-events-none fixed -left-32 top-36 h-72 w-72 rounded-full bg-[#22C55E]/15 blur-[100px]" />
      <div className="pointer-events-none fixed -right-32 top-1/3 h-80 w-80 rounded-full bg-[#22D3EE]/15 blur-[110px]" />

      <aside className="fixed inset-y-0 left-0 z-40 hidden w-64 flex-col border-r border-border bg-card lg:flex">
        <div className="border-b border-border px-5 py-5">
          <Link to="/" className="flex items-center gap-3 rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#0F9488] focus-visible:ring-offset-2">
            <img src="/logo.png" alt="" className="h-10 w-10 shrink-0 object-contain" />
            <span className="min-w-0">
              <span className="block font-display text-xl leading-tight tracking-tight text-foreground">HarborAI</span>
              <span className="mt-1 block truncate text-[10px] font-bold uppercase tracking-[0.14em] text-[#0F766E]">{workspaceLabel}</span>
            </span>
          </Link>
        </div>

        <nav aria-label={copy("Workspace navigation")} className="dashboard-nav-scrollbar min-h-0 flex-1 overflow-y-auto px-3 py-4">
          {desktopSections.filter((section) => section.items.length > 0).map((section) => (
            <section key={section.label} className="mb-5 last:mb-0">
              <h2 className="px-3 pb-2 text-[10px] font-bold uppercase tracking-[0.14em] text-[#788693]">{section.label}</h2>
              <div className="space-y-1">
                {section.items.map((item) => {
                  const Icon = item.icon;
                  const active = activeNavigationId
                    ? activeNavigationId === item.id
                    : activeTab === (item.tabId ?? item.id);
                  return <button
                    key={item.id}
                    type="button"
                    onClick={() => {
                      onTabChange(item.id);
                      if (item.anchorId) {
                        window.setTimeout(() => document.getElementById(item.anchorId!)?.scrollIntoView({ behavior: "smooth", block: "start" }), 350);
                      }
                    }}
                    aria-current={active ? "page" : undefined}
                    className={cn(
                      "group flex min-h-10 w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-sm font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#0F9488] focus-visible:ring-offset-2",
                      active
                        ? "bg-[#E6F4F2] text-[#0D706B]"
                        : "text-muted-foreground hover:bg-[#F1F5F4] hover:text-foreground",
                    )}
                  >
                    <Icon className={cn("h-[18px] w-[18px] shrink-0", active ? "text-[#0F9488]" : "text-[#71808C] group-hover:text-[#0F9488]")} aria-hidden="true" />
                    <span className="min-w-0 flex-1 truncate">{item.label}</span>
                    {active && <span aria-hidden="true" className="h-1.5 w-1.5 shrink-0 rounded-full bg-[#0F9488]" />}
                  </button>;
                })}
              </div>
            </section>
          ))}
        </nav>

        <div className="border-t border-border p-3">
          <div className="flex min-w-0 items-center gap-3 px-2 py-2">
            <Avatar className="h-9 w-9 shrink-0">
              <AvatarFallback className="bg-[#EAF4F2] font-bold text-foreground">{userName.charAt(0).toUpperCase()}</AvatarFallback>
            </Avatar>
            <div className="min-w-0">
              <p className="truncate text-sm font-bold text-foreground">{userName}</p>
              <p className="truncate text-xs text-muted-foreground">{workspaceLabel}</p>
            </div>
          </div>
          <div className="mt-1">
            <Button type="button" variant="ghost" className="min-h-9 w-full justify-start px-2 text-xs font-semibold text-[#B42318] hover:bg-[#FEE4E2] hover:text-[#B42318]" onClick={onLogout}>
              <LogOut className="mr-2 h-4 w-4" aria-hidden="true" /><DashboardText text="Logout" /></Button>
          </div>
        </div>
      </aside>

      <header className="sticky top-0 z-30 border-b border-border bg-background/95 shadow-sm backdrop-blur-sm lg:ml-64 lg:w-[calc(100%-16rem)]">
        <div className="h-1 bg-gradient-to-r from-[#22C55E] via-[#0F9488] to-[#22D3EE]" />
        <div className="container mx-auto flex min-h-16 items-center gap-4 px-4 py-3 lg:px-6">
          <Link to="/" className="flex shrink-0 items-center gap-2 text-foreground sm:gap-3 lg:hidden">
            <img src="/logo.png" alt="HarborAI Logo" className="h-10 w-10 object-contain sm:h-11 sm:w-11" />
            <div className="hidden sm:block">
              <p className="font-display text-xl tracking-tight">HarborAI</p>
              <p className="text-[10px] font-bold uppercase tracking-[0.16em] text-[#0F9488]">{t("shell.dashboard")}</p>
            </div>
          </Link>

          <div className="hidden min-w-0 flex-1 lg:block">
            <p className="truncate font-display text-lg leading-tight text-foreground">{title}</p>
            <p className="truncate text-xs text-muted-foreground">{subtitle}</p>
          </div>

          <div className="ml-auto flex shrink-0 items-center gap-2">
            <Button
              type="button"
              variant="ghost"
              size="icon"
              className={`min-h-11 min-w-11 rounded-full text-foreground hover:bg-[#22C55E]/20 hover:text-[#15803D] lg:hidden ${hasMobileDock ? "hidden" : ""}`}
              onClick={() => setSidebarOpen((open) => !open)}
              aria-label={copy("Open dashboard navigation")}
              aria-expanded={sidebarOpen}
              aria-controls="admin-mobile-navigation"
              title={copy("Dashboard navigation")}
            >
              {sidebarOpen ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
            </Button>
              <Button
              variant="ghost"
              size="icon"
              className="rounded-full text-foreground hover:bg-[#22C55E]/20 hover:text-[#15803D]"
              onClick={() => onTabChange("faq")}
              aria-label={copy("Tulong at FAQ")}
              title={copy("Tulong at FAQ")}
            >
              <CircleHelp className="h-5 w-5" />
            </Button>
            <ThemeSelector /><LanguageSwitcher />
            <Button variant="ghost" size="icon" aria-label={t("appearance.settings")} onClick={() => setAppearanceOpen(true)}><span aria-hidden="true">⚙</span></Button>
            <DropdownMenu open={notificationsOpen} onOpenChange={(open) => { setNotificationsOpen(open); if (open) void loadNotifications(true); }}>
              <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" className="relative rounded-full text-foreground hover:bg-[#22C55E]/20 hover:text-[#15803D]" aria-label={unreadCount ? copy("Notifications, {0} unread", [unreadCount]) : copy("Notifications")}>
                  <Bell className="h-5 w-5" />
                  {unreadCount > 0 && <span aria-hidden="true" className="absolute right-1 top-1 h-2.5 w-2.5 rounded-full border-2 border-[#F5F1E5] bg-red-600" />}
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" className="max-h-[min(75vh,32rem)] w-[min(20rem,calc(100vw-1rem))] overflow-y-auto">
                <DropdownMenuLabel className="font-bold text-foreground">{t("shell.notifications")}</DropdownMenuLabel>
                {notificationsError && <DropdownMenuItem disabled className="whitespace-normal text-red-700">{copy(notificationsError)}</DropdownMenuItem>}
                {notificationsLoading && <DropdownMenuItem disabled><DashboardText text="Loading notifications…" /></DropdownMenuItem>}
                {!notificationsLoading && notifications.length === 0 && <DropdownMenuItem disabled>{t("shell.noNotifications")}</DropdownMenuItem>}
                {notifications.map((item) => <DropdownMenuItem key={item.id} disabled={notificationReadBusy} className={cn("block cursor-pointer whitespace-normal py-2", !item.read_at && "bg-red-50/70")} onSelect={async (event) => {
                  event.preventDefault();
                  if(notificationReads.current.size>0)return;
                  if (!item.read_at) {
                    notificationReads.current.add(item.id);
                    setNotificationReadBusy(true);
                    try {
                      const result = await request(`/api/notifications/${item.id}/read`, { method: 'PATCH' });
                      notificationLoads.current.invalidate();
                      setNotificationsLoading(false);
                      setNotifications(current => current.map(n => n.id === item.id ? { ...n, read_at: result.read_at ?? new Date().toISOString() } : n));
                      setUnreadCount(Number.isFinite(Number(result?.unread_count)) ? Number(result.unread_count) : count => Math.max(0, count - 1));
                      setNotificationsError("");
                    } catch {
                      setNotificationsError("This notification could not be marked as read. Please try again.");
                      return;
                    } finally {notificationReads.current.delete(item.id);setNotificationReadBusy(false);}
                  }
                  const destination = notificationTarget(item, userRole === 'Producer');
                  const target = destination ? tabs.find(t => t.id === destination.tab) : undefined;
                  if (target && destination) {
                    sessionStorage.setItem('harbor:notification', JSON.stringify(destination));
                    window.dispatchEvent(new Event('harbor:notification'));
                    onTabChange(target.id);
                  }
                  setNotificationsOpen(false);
                }}><span className="flex items-center gap-2 font-bold text-foreground">{!item.read_at && <span className="h-2 w-2 shrink-0 rounded-full bg-red-600" />}{copy(item.title)}</span><span className="mt-0.5 block text-xs text-muted-foreground">{notificationBody(item,copy)}</span>{notificationTarget(item, userRole === 'Producer') && <span className="mt-1 block text-[10px] font-bold uppercase tracking-wide text-[#0F9488]">{notificationTarget(item)?.tab === 'demands' ? copy("Review Demand") : copy("Open")}</span>}<span className="mt-1 block text-[10px] text-muted-foreground">{new Date(item.created_at).toLocaleString()}</span></DropdownMenuItem>)}
              </DropdownMenuContent>
            </DropdownMenu>
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button
                  variant="ghost"
                  className="h-auto rounded-full px-2 py-1.5 text-left hover:bg-[#22C55E]/15 sm:px-3 lg:hidden"
                  aria-label={copy("Open profile menu for {0}", [userName])}
                >
                  <Avatar className="h-9 w-9 ring-2 ring-[#22C55E]/50">
                    <AvatarFallback className="bg-[#FDE68A] font-bold text-foreground">{userName.charAt(0).toUpperCase()}</AvatarFallback>
                  </Avatar>
                  <span className="hidden max-w-32 md:block">
                    <span className="block truncate text-sm font-bold text-foreground">{userName}</span>
                    <span className="block truncate text-xs text-muted-foreground">{userRole}</span>
                  </span>
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" sideOffset={8} className="w-60">
                <DropdownMenuItem className="font-bold text-foreground">{userName}</DropdownMenuItem>
                <DropdownMenuItem className="text-xs text-muted-foreground">{userRole}</DropdownMenuItem>
                <DropdownMenuItem
                  onClick={onLogout}
                  className="mt-1 border-t border-border pt-2 font-bold text-[#B42318] focus:bg-[#FEE4E2] focus:text-[#B42318]"
                >
                  <LogOut className="mr-2 h-4 w-4" /><DashboardText text="Logout" /></DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        </div>

        {!hasMobileDock && <Sheet open={sidebarOpen} onOpenChange={setSidebarOpen}>
          <SheetContent side="left" className="w-[min(22rem,calc(100vw-1rem))] bg-background text-foreground [&>button]:min-h-11 [&>button]:min-w-11">
            <SheetHeader className="border-b border-border pr-16">
              <SheetTitle>{workspaceLabel}</SheetTitle>
              <SheetDescription><DashboardText text="Choose a workspace section." /></SheetDescription>
            </SheetHeader>
            <nav id="admin-mobile-navigation" aria-label={copy("Mobile dashboard navigation")} className="min-h-0 flex-1 space-y-5 overflow-y-auto px-4 pb-[max(env(safe-area-inset-bottom),1rem)]">
              {desktopSections.map(section => <section key={section.label}>
                <h2 className="mb-2 text-xs font-bold uppercase tracking-wide text-muted-foreground">{section.label}</h2>
                {section.items.map(item => {
                  const Icon = item.icon;
                  const active = activeNavigationId ? activeNavigationId === item.id : activeTab === (item.tabId ?? item.id);
                  return <button key={item.id} type="button" aria-current={active ? 'page' : undefined}
                    className={cn('mb-1 flex min-h-11 w-full items-center gap-3 rounded-xl px-3 py-3 text-left text-sm font-semibold focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#0F9488]', active ? 'bg-[#E6F4F2] text-[#0D706B]' : 'hover:bg-card')}
                    onClick={() => { setSidebarOpen(false); onTabChange(item.id); if (item.anchorId) window.setTimeout(() => document.getElementById(item.anchorId!)?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 350); }}>
                    <Icon className="h-5 w-5 shrink-0" aria-hidden="true" /><span className="min-w-0 break-words">{item.label}</span>
                  </button>;
                })}
              </section>)}
              <button type="button" className="min-h-11 w-full rounded-xl border border-border px-3 text-left text-sm font-semibold" onClick={() => { setSidebarOpen(false); onTabChange('faq'); }}><DashboardText text="Help & FAQ" /></button>
            </nav>
          </SheetContent>
        </Sheet>}

      </header>

      <main className={`relative z-10 min-h-[calc(100vh-4rem)] min-w-0 w-full p-3 font-body sm:p-6 lg:ml-64 lg:w-[calc(100%-16rem)] lg:p-8 ${hasMobileDock ? "pb-24 sm:pb-24 lg:pb-8" : ""}`}>
        {activeTab === "faq" ? <section className="mx-auto max-w-4xl space-y-5 rounded-2xl border border-border bg-card p-5 shadow-sm sm:p-8">
          <div className="flex items-start justify-between gap-3"><div><p className="text-sm font-bold uppercase tracking-wide text-[#0F9488]">{userRole}{' '}<DashboardText text="Help Center" /></p><h1 className="mt-1 font-display text-2xl text-foreground sm:text-3xl"><DashboardText text="Frequently Asked Questions" /></h1></div><Button variant="outline" className="min-h-11 shrink-0" onClick={() => onTabChange(tabs[0]?.id || "dashboard")}><DashboardText text="Back" /></Button></div>
          <p className="text-sm leading-6 text-muted-foreground"><DashboardText text="Answers and guidance for using HarborAI as a" />{' '}{userRole.toLowerCase()}.</p>
          {faqCategories.map((category) => <div key={category.id}><h2 className="mb-2 font-bold text-[#0F9488]">{category.label}</h2><Accordion type="single" collapsible className="w-full">{category.items.map((item, index) => <AccordionItem key={item.question} value={`${category.id}-${index}`}><AccordionTrigger className="min-h-11 text-left text-sm font-semibold text-foreground">{item.question}</AccordionTrigger><AccordionContent className="text-sm leading-6 text-muted-foreground">{item.answer}</AccordionContent></AccordionItem>)}</Accordion></div>)}
        </section> : children}
      </main>
      {hasMobileDock && <nav aria-label={copy("Mobile dashboard navigation")} className="fixed inset-x-0 bottom-0 z-40 border-t border-border bg-card/95 px-2 pt-2 shadow-[0_-6px_24px_rgba(18,60,92,0.12)] backdrop-blur lg:hidden" style={{ paddingBottom: "max(env(safe-area-inset-bottom), 0.5rem)" }}>
        <div className="mx-auto flex max-w-xl items-stretch justify-around">
          {mobileTabs.map((tab) => { const Icon = tab.icon; const selected = activeTab === tab.id; return <button key={tab.id} type="button" onClick={() => onTabChange(tab.id)} aria-label={tab.label} title={tab.label} aria-current={selected ? "page" : undefined} className={`flex min-h-11 min-w-0 flex-1 flex-col items-center justify-center gap-0.5 rounded-xl px-1 text-[10px] font-bold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#0F9488] ${selected ? "text-[#0F9488]" : "text-muted-foreground"}`}><Icon className="h-5 w-5" aria-hidden="true"/><span className="max-w-full truncate">{tab.label}</span></button>; })}
          <DropdownMenu>
            <DropdownMenuTrigger asChild><button type="button" aria-label={copy("More dashboard sections")} className={`flex min-h-11 min-w-0 flex-1 flex-col items-center justify-center gap-0.5 rounded-xl px-1 text-[10px] font-bold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#0F9488] ${mobileMoreTabs.some((tab) => tab.id === activeTab) ? "text-[#0F9488]" : "text-muted-foreground"}`}><MoreHorizontal className="h-5 w-5"/><span><DashboardText text="More" /></span></button></DropdownMenuTrigger>
            <DropdownMenuContent align="end" side="top" className="mb-2 w-56">{mobileMoreTabs.map((tab) => { const Icon = tab.icon; return <DropdownMenuItem key={tab.id} onClick={() => onTabChange(tab.id)} className="min-h-11"><Icon className="mr-2 h-4 w-4"/>{tab.label}</DropdownMenuItem>; })}</DropdownMenuContent>
          </DropdownMenu>
        </div>
      </nav>}
    </div>
    </DashboardPortalTheme.Provider>
  );
}