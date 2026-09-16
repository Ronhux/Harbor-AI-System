import { Link } from 'react-router-dom';
import { Brain, Building2, CircleHelp, Headphones, Package, Sprout, Users } from 'lucide-react';
import { useState } from 'react';
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '../components/ui/accordion';
import { Button } from '../components/ui/button';
import { Card, CardContent } from '../components/ui/card';
import { faqCategories, type FaqCategoryId } from '../data/faq';

const categoryIcons = {
  general: CircleHelp,
  producer: Sprout,
  buyer: Building2,
  orders: Package,
  insights: Brain,
  support: Headphones,
};

export default function FaqPage() {
  const [activeCategory, setActiveCategory] = useState<FaqCategoryId>('general');
  const category = faqCategories.find((item) => item.id === activeCategory) ?? faqCategories[0];
  const CategoryIcon = categoryIcons[category.id];

  return (
    <div className="min-h-screen bg-[#F5F1E5] font-body">
      <style>{`
        @import url('https://fonts.googleapis.com/css2?family=Anton&family=Baloo+2:wght@400;500;600;700;800&display=swap');
        .font-display { font-family: 'Anton', ui-sans-serif, sans-serif; }
        .font-body { font-family: 'Baloo 2', ui-rounded, system-ui, sans-serif; }
      `}</style>

      <header className="border-b border-[#E7E1D0] bg-[#F5F1E5]/95 backdrop-blur-sm shadow-sm">
        <div className="container mx-auto flex min-h-16 items-center justify-between gap-4 px-4 py-3">
          <Link to="/" className="flex items-center gap-3 text-[#123C5C]">
            <img src="/logo.png" alt="HarborAI Logo" className="h-11 w-11 object-contain" />
            <span className="font-display text-xl tracking-tight">HarborAI</span>
          </Link>
          <div className="flex items-center gap-2">
            <Link to="/login">
              <Button variant="outline" className="rounded-full font-bold border-2 border-[#123C5C] text-[#123C5C] hover:bg-[#123C5C] hover:text-white">
                Login
              </Button>
            </Link>
            <Link to="/register">
              <Button className="rounded-full font-bold bg-[#22C55E] hover:bg-[#15803D]">Register</Button>
            </Link>
          </div>
        </div>
      </header>

      <main className="container mx-auto max-w-6xl px-4 py-10 sm:py-14">
        <section className="mx-auto max-w-3xl text-center">
          <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-[#0F9488]/15 text-[#0F9488]">
            <CircleHelp className="h-7 w-7" />
          </div>
          <h1 className="font-display text-3xl tracking-tight text-[#123C5C] sm:text-4xl">HarborAI Help Center</h1>
          <p className="mt-3 text-base leading-7 text-[#45586B] sm:text-lg">Malinaw na sagot tungkol sa mga feature na available ngayon sa HarborAI.</p>
        </section>

        <section className="mt-10 grid gap-6 lg:grid-cols-[260px_minmax(0,1fr)]">
          <nav aria-label="FAQ categories" className="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:flex lg:flex-col">
            {faqCategories.map((item) => {
              const Icon = categoryIcons[item.id];
              const isActive = item.id === category.id;
              return (
                <button
                  key={item.id}
                  type="button"
                  onClick={() => setActiveCategory(item.id)}
                  className={`flex min-h-20 items-center gap-3 rounded-xl border p-3 text-left text-sm font-medium transition ${isActive ? 'border-[#0F9488] bg-[#0F9488] text-white shadow-md' : 'border-[#E7E1D0] bg-white text-[#45586B] hover:border-[#0F9488]/40 hover:bg-[#0F9488]/5'}`}
                >
                  <Icon className="h-5 w-5 shrink-0" />
                  <span>{item.label}</span>
                </button>
              );
            })}
          </nav>

          <Card className="border-[#E7E1D0] shadow-sm">
            <CardContent className="p-5 sm:p-7">
              <div className="mb-5 flex items-start gap-3 border-b border-[#E7E1D0] pb-5">
                <div className="rounded-xl bg-[#0F9488]/15 p-3 text-[#0F9488]"><CategoryIcon className="h-6 w-6" /></div>
                <div>
                  <h2 className="font-display text-2xl tracking-tight text-[#123C5C]">{category.label}</h2>
                  <p className="mt-1 text-sm text-[#45586B]">{category.description}</p>
                </div>
              </div>
              <Accordion type="single" collapsible className="w-full">
                {category.items.map((item, index) => (
                  <AccordionItem key={item.question} value={`${category.id}-${index}`} className="border-[#E7E1D0]">
                    <AccordionTrigger className="py-5 text-base font-semibold text-[#123C5C] hover:no-underline hover:text-[#0F9488]">
                      {item.question}
                    </AccordionTrigger>
                    <AccordionContent className="max-w-3xl pb-5 text-base leading-7 text-[#45586B]">
                      {item.answer}
                    </AccordionContent>
                  </AccordionItem>
                ))}
              </Accordion>
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