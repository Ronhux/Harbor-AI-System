import { Link, useLocation } from 'react-router-dom';
import { ArrowLeft, Mail, Phone, Sprout } from 'lucide-react';
import { Button } from '../components/ui/button';

const contact = [
  { name: 'Huxel Rohn L. Palmea' },
  { name: 'Veronica C. Cervantes' },
  { name: 'Melody O. Furagganan' },
];

const content: Record<string, { title: string; intro: string; sections: [string, string][] }> = {
  '/help': { title: 'HarborAI Help Center', intro: 'Guides for using HarborAI’s producer and institutional buyer tools.', sections: [
    ['Getting started', 'Producer registration requires matching registry details and then administrator review. Wait for approval before signing in with your registered mobile number or optional email and password. A registry match alone does not verify identity or mobile ownership. Your available dashboard depends on your account role.'],
    ['Producer guide', 'Use My Listings to add and maintain product name, category, quantity, unit, price, location, harvest details, and listing status. Keep availability accurate so buyers can make informed requests.'],
    ['Institutional buyer guide', 'Browse producer listings, review product and availability details, and place orders. Use demand requests to describe the product, quantity, target price, location, and deadline you need.'],
    ['Orders and requests', 'Review order and demand status in your dashboard. If details need correction, contact the other party or HarborAI support and include the relevant order or request number.'],
    ['Profile and common problems', 'Check your sign-in details and profile information first. If a page fails to load, retry later and contact support with the page name and a short description of the issue.'],
    ['FAQs and support', 'Use the FAQ page for common questions about accounts, listings, orders, and marketplace activity. If account access or order details need review, contact HarborAI support with your account information and the relevant order or request number.'],
  ] },
  // `/terms` is served by the full Terms and Conditions page
  // (src/app/pages/TermsAndConditionsPage.tsx), not by this generic page.
  '/data-protection': { title: 'Data Protection', intro: 'HarborAI aims to handle platform information responsibly and limit access to authorized use.', sections: [
    ['Information and use', 'The application stores account details, producer and buyer profile information, product listings, demand requests, and order information needed for the features available in the system. Information is used to operate accounts, listings, orders, and platform administration.'],
    ['Access and security', 'Authentication protects account access, and administrative routes require an authorized account role. Users should protect their credentials. No online system can guarantee absolute security.'],
    ['Sharing and retention', 'Information may be visible to users when needed for marketplace activity, such as producer and listing details shown to buyers. Records are retained in the system database; retention periods and additional operational controls depend on deployment configuration.'],
    ['Rights and incidents', 'For requests about your information or to report a suspected data incident, contact the HarborAI project contacts. Requests may require account verification before action.'],
    ['Updates', 'This page may be updated as HarborAI’s implementation and operating practices change.'],
  ] },
};

export default function PublicInfoPage() {
  const path = useLocation().pathname;
  if (path === '/contact') return <main className="min-h-screen bg-background px-4 py-12 text-foreground sm:px-6"><div className="mx-auto max-w-5xl"><Header title="Contact Us"/><p className="mb-8 text-muted-foreground">Contact the HarborAI project team for system support.</p><div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">{contact.map(person => <article key={person.name} className="rounded-2xl border border-border bg-card p-6 shadow-sm"><h2 className="text-xl font-bold">{person.name}</h2><p className="mt-4 text-muted-foreground">Contact details are available through the project owner.</p></article>)}</div></div></main>;
  const page = content[path] || content['/help'];
  return <main className="min-h-screen bg-background px-4 py-12 text-foreground sm:px-6"><article className="mx-auto max-w-4xl"><Header title={page.title}/><p className="mb-8 text-lg text-muted-foreground">{page.intro}</p><div className="space-y-4">{page.sections.map(([heading, body])=><section key={heading} className="rounded-2xl border border-border bg-card p-5 shadow-sm sm:p-7"><h2 className="text-xl font-bold">{heading}</h2><p className="mt-2 leading-relaxed text-muted-foreground">{body}</p></section>)}</div></article></main>;
}

function Header({ title }: { title: string }) { return <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"><div className="flex items-center gap-3"><Sprout className="h-9 w-9 shrink-0 text-[#0F9488]"/><div className="min-w-0"><Link to="/" className="text-xs font-bold uppercase tracking-widest text-[#0F9488]">HarborAI</Link><h1 className="font-display text-2xl sm:text-3xl md:text-4xl">{title}</h1></div></div><Link to="/"><Button variant="outline" className="min-h-11 shrink-0 self-start rounded-xl sm:self-auto"><ArrowLeft className="mr-2 h-4 w-4"/>Home</Button></Link></div>; }
