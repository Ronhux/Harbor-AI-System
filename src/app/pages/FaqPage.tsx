import { Link } from 'react-router-dom';
import {
  Brain,
  Building2,
  CalendarDays,
  CircleHelp,
  Headphones,
  Landmark,
  Package,
  Search,
  ShieldCheck,
  UserPlus,
  Users,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '../components/ui/accordion';
import { Button } from '../components/ui/button';
import { Card, CardContent } from '../components/ui/card';
import { faqCategories, type FaqCategoryId } from '../data/faq';

const categoryIcons = {
  general: CircleHelp,
  registration: UserPlus,
  kadiwa: CalendarDays,
  fca: Landmark,
  buyer: Building2,
  orders: Package,
  insights: Brain,
  privacy: ShieldCheck,
  support: Headphones,
} satisfies Record<FaqCategoryId, typeof CircleHelp>;

export default function FaqPage() {
  const [activeCategory, setActiveCategory] = useState<FaqCategoryId>('general');
  const [query, setQuery] = useState('');
  const category = faqCategories.find((item) => item.id === activeCategory) ?? faqCategories[0];
  const CategoryIcon = categoryIcons[category.id];

  const term = query.trim().toLowerCase();
  const results = useMemo(() => {
    if (!term) return [];
    return faqCategories.flatMap((group) =>
      group.items
        .filter((item) => `${item.question} ${item.answer}`.toLowerCase().includes(term))
        .map((item) => ({ item, group })),
    );
  }, [term]);
  const isSearching = term.length > 0;

  return (
    <div className="min-h-screen bg-background font-body">
      <style>{`
        @import url('https://fonts.googleapis.com/css2?family=Anton&family=Baloo+2:wght@400;500;600;700;800&display=swap');
        .font-display { font-family: 'Anton', ui-sans-serif, sans-serif; }
        .font-body { font-family: 'Baloo 2', ui-rounded, system-ui, sans-serif; }
      `}</style>

      <header className="border-b border-border bg-background/95 backdrop-blur-sm shadow-sm">
        <div className="container mx-auto flex min-h-16 flex-wrap items-center justify-between gap-3 px-4 py-3">
          <Link to="/" className="flex shrink-0 items-center gap-3 text-foreground">
            <img src="/logo.png" alt="HarborAI Logo" className="h-10 w-10 object-contain min-[420px]:h-11 min-[420px]:w-11" />
            <span className="font-display text-lg tracking-tight min-[420px]:text-xl">HarborAI</span>
          </Link>
          <div className="flex flex-wrap items-center justify-end gap-2">
            <Link to="/">
              <Button variant="outline" className="min-h-11 rounded-full font-bold border-2 border-[#123C5C] text-foreground hover:bg-[#123C5C] hover:text-white">
                <span className="hidden sm:inline">Back to </span>Home
              </Button>
            </Link>
            <Link to="/login">
              <Button variant="outline" className="min-h-11 rounded-full font-bold border-2 border-[#123C5C] text-foreground hover:bg-[#123C5C] hover:text-white">
                Login
              </Button>
            </Link>
            <Link to="/register">
              <Button className="min-h-11 rounded-full font-bold bg-[#22C55E] hover:bg-[#15803D]">Register</Button>
            </Link>
          </div>
        </div>
      </header>

      <main className="container mx-auto max-w-6xl px-4 py-10 sm:py-14">
        <section className="mx-auto max-w-3xl text-center">
          <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-[#0F9488]/15 text-[#0F9488]">
            <CircleHelp className="h-7 w-7" />
          </div>
          <h1 className="font-display text-2xl tracking-tight text-foreground min-[380px]:text-3xl sm:text-4xl">HarborAI Help Center</h1>
          <p className="mt-3 text-base leading-7 text-muted-foreground sm:text-lg">Malinaw na sagot tungkol sa mga feature na available ngayon sa HarborAI.</p>
        </section>

        <section className="mt-10 grid gap-6 lg:grid-cols-[260px_minmax(0,1fr)]">
          <div className="min-w-0 space-y-4">
            <div className="relative">
              <Search aria-hidden="true" className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
              <input
                type="search"
                value={query}
                onChange={(event) => setQuery(event.target.value)}
                placeholder="Maghanap ng tanong..."
                aria-label="Maghanap sa mga FAQ"
                className="min-h-11 w-full rounded-xl border border-border bg-card pl-9 pr-3 text-sm text-foreground outline-none focus:border-[#0F9488] focus:ring-2 focus:ring-[#0F9488]/20"
              />
            </div>
            <nav aria-label="FAQ categories" className="grid grid-cols-1 gap-2 min-[420px]:grid-cols-2 sm:grid-cols-3 lg:flex lg:flex-col">
              {faqCategories.map((item) => {
                const Icon = categoryIcons[item.id];
                const isActive = !isSearching && item.id === category.id;
                return (
                  <button
                    key={item.id}
                    type="button"
                    onClick={() => { setActiveCategory(item.id); setQuery(''); }}
                    className={`flex min-h-14 items-center gap-3 rounded-xl border p-3 text-left text-sm font-medium transition ${isActive ? 'border-[#0F9488] bg-[#0F9488] text-white shadow-md' : 'border-border bg-card text-muted-foreground hover:border-[#0F9488]/40 hover:bg-[#0F9488]/5'}`}
                  >
                    <Icon className="h-5 w-5 shrink-0" />
                    <span>{item.label}</span>
                  </button>
                );
              })}
            </nav>
          </div>

          <Card className="border-border shadow-sm">
            <CardContent className="p-5 sm:p-7">
              <div className="mb-5 flex items-start gap-3 border-b border-border pb-5">
                <div className="rounded-xl bg-[#0F9488]/15 p-3 text-[#0F9488]">{isSearching ? <Search className="h-6 w-6" /> : <CategoryIcon className="h-6 w-6" />}</div>
                <div className="min-w-0">
                  <h2 className="font-display text-2xl tracking-tight text-foreground">{isSearching ? 'Mga resulta' : category.label}</h2>
                  <p className="mt-1 text-sm text-muted-foreground">{isSearching ? `${results.length} na tanong ang tumugma sa "${query.trim()}".` : category.description}</p>
                </div>
              </div>

              {isSearching ? (
                results.length === 0 ? (
                  <p className="py-8 text-center text-sm text-muted-foreground">Walang nahanap na tanong. Subukang ibang salita.</p>
                ) : (
                  <Accordion type="single" collapsible className="w-full">
                    {results.map(({ item, group }, index) => (
                      <AccordionItem key={`${group.id}-${index}`} value={`search-${group.id}-${index}`} className="border-border">
                        <AccordionTrigger className="py-5 text-base font-semibold text-foreground hover:no-underline hover:text-[#0F9488]">
                          {item.question}
                        </AccordionTrigger>
                        <AccordionContent className="max-w-3xl pb-5 text-base leading-7 text-muted-foreground">
                          <p className="mb-2 text-xs font-semibold uppercase tracking-[0.1em] text-[#0F9488]">{group.label}</p>
                          {item.answer}
                        </AccordionContent>
                      </AccordionItem>
                    ))}
                  </Accordion>
                )
              ) : (
                <Accordion type="single" collapsible className="w-full">
                  {category.items.map((item, index) => (
                    <AccordionItem key={item.question} value={`${category.id}-${index}`} className="border-border">
                      <AccordionTrigger className="py-5 text-base font-semibold text-foreground hover:no-underline hover:text-[#0F9488]">
                        {item.question}
                      </AccordionTrigger>
                      <AccordionContent className="max-w-3xl pb-5 text-base leading-7 text-muted-foreground">
                        {item.answer}
                      </AccordionContent>
                    </AccordionItem>
                  ))}
                </Accordion>
              )}
            </CardContent>
          </Card>
        </section>

        <section className="mt-8 rounded-2xl border border-[#0F9488]/30 bg-[#0F9488]/5 p-5 text-center text-sm leading-6 text-[#0B4842] sm:p-6">
          <Users className="mx-auto mb-2 h-5 w-5 text-[#0F9488]" />
          HarborAI features continue to evolve. This help center describes the functionality currently available in the system.
        </section>
      </main>
    </div>
  );
}