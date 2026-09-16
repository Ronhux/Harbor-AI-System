import { Link } from 'react-router-dom';
import { ShieldCheck } from 'lucide-react';
import { Button } from '../components/ui/button';

const sections = [
  {
    title: '1. WHO WE ARE',
    content: [
      'HarborAI ("HarborAI," "we," "us," or "our") is a digital agriculture trading and enterprise platform designed to help registered farmers connect with buyers and institutional buyers.',
      'Access to the farmer features of HarborAI is intended for registered or verified farmers. To support farmer registration and verification, certain farmer information may be obtained or made available through authorized records from the Department of Agriculture (DA) and/or the Municipal Agriculture Office (MAO).',
      'This information may serve as default or reference information when creating and verifying a farmer\'s HarborAI account. It is not necessarily information that HarborAI collects directly from the farmer.',
      'The Registry System for Basic Sectors in Agriculture (RSBSA) number may be used as a reference for verifying a farmer\'s registration and eligibility to access the farmer features of HarborAI.',
      'HarborAI acts as the Personal Information Controller for personal information that it controls or processes through the Service, where applicable.',
      'For privacy-related questions, concerns, or requests, users may contact the HarborAI administrator through the contact information provided within the system.',
    ],
  },
  {
    title: '2. INFORMATION WE PROCESS',
    intro: 'HarborAI processes information necessary to provide and operate the Service.',
    subsections: [
      ['Farmer registration and verification information', 'For registered farmers, certain information may be provided, pre-populated, verified, or referenced through authorized DA and/or MAO records.', ['Full name', 'Registered address or location', 'Contact information, where available', 'Type of agricultural activity', 'Farm-related information', 'RSBSA number', 'Other registration information necessary to verify the farmer\'s eligibility to use HarborAI'], 'This information is primarily used to verify a farmer\'s registration and eligibility to access the Service. HarborAI does not represent that it independently collected all of this information directly from the farmer.'],
      ['Account information', 'Information required to create and manage a HarborAI account may include:', ['Username', 'Email address', 'Mobile number', 'Account type', 'Password'], 'Passwords are protected using appropriate security measures, including hashing where applicable.'],
      ['Product information', 'When a registered farmer creates a product listing, HarborAI may process:', ['Product name', 'Product category', 'Product description', 'Quantity', 'Price', 'Availability', 'Production or harvest information', 'Product images', 'Other information provided for the purpose of creating a listing']],
      ['Buyer information', 'For buyers using HarborAI, we may process:', ['Name', 'Contact information', 'Business or organization name', 'Business address', 'Buyer type', 'Other information necessary to process transactions']],
      ['Institutional buyer information', 'For institutional buyers, we may process:', ['Organization or business name', 'Authorized representative\'s name', 'Contact information', 'Business address', 'Registration or identification information', 'Information submitted through an institutional buyer application']],
      ['Order information', 'We may process:', ['Products ordered', 'Quantities', 'Prices', 'Order status', 'Delivery information', 'Transaction records', 'Order history']],
      ['Payment information', 'Where applicable, HarborAI may process:', ['Payment method', 'Transaction or reference number', 'Payment amount', 'Uploaded proof of payment'], 'HarborAI does not intentionally collect or store complete payment passwords, PINs, or other sensitive payment credentials.'],
      ['Delivery information', 'Where delivery is supported, we may process:', ['Delivery address', 'Municipality or city', 'Province', 'Contact information', 'Delivery instructions']],
      ['Technical information', 'HarborAI may automatically process limited technical information such as:', ['Device type', 'Browser information', 'IP address', 'Login information', 'System activity or session logs']],
    ],
  },
  {
    title: '3. HOW WE USE YOUR INFORMATION',
    intro: 'We process information for the following purposes:',
    bullets: ['Verify the registration and eligibility of farmers', 'Use authorized DA and/or MAO information as default or reference information for farmer accounts', 'Verify farmer registration using the RSBSA number and other relevant registration information', 'Create and manage HarborAI accounts', 'Authenticate users and maintain account security', 'Allow registered farmers to create and manage product listings', 'Allow buyers to browse and purchase available agricultural products', 'Process, confirm, and manage orders and transactions', 'Facilitate communication between buyers and farmers', 'Process institutional buyer applications', 'Verify submitted information and supporting documents', 'Process and verify payments and proof of payment', 'Arrange and coordinate product delivery', 'Send notifications regarding orders, listings, payments, applications, and deliveries', 'Maintain transaction and order history', 'Prevent fraud, misuse, unauthorized access, and other security incidents', 'Generate reports and records necessary for operating the system', 'Improve the functionality, reliability, and usability of HarborAI', 'Comply with applicable legal, regulatory, accounting, and reporting requirements'],
    content: ['The legal basis for processing may include the performance of a service or transaction, compliance with legal obligations, legitimate interests, and consent where required by law. Under the Data Privacy Act, lawful processing may be based on several grounds, including consent, contract, legal obligation, public authority functions, and legitimate interests, depending on the specific processing activity.'],
  },
  {
    title: '4. HOW WE SHARE YOUR INFORMATION',
    intro: 'HarborAI shares personal information only when necessary to provide the Service, verify eligibility, complete transactions, maintain system security, or comply with applicable laws.',
    bullets: ['Department of Agriculture (DA) and/or Municipal Agriculture Office (MAO) when necessary for authorized farmer registration verification, record validation, or related agricultural purposes', 'Farmers who need relevant buyer or order information to fulfill transactions', 'Buyers and institutional buyers who need relevant farmer, product, order, or delivery information to complete transactions', 'Delivery or logistics personnel who require delivery information', 'Payment service providers or banking channels when necessary to verify payments and transactions', 'System administrators and authorized personnel who require access to operate, maintain, and secure HarborAI', 'Government agencies and authorities when disclosure is required or permitted by law', 'Service providers that assist with hosting, system maintenance, communication, security, analytics, or other technical services, subject to appropriate data protection obligations', 'Professional advisers or auditors, when necessary and subject to applicable confidentiality obligations'],
    content: ['We do not sell or rent your personal information to third parties.', 'HarborAI only shares information that is reasonably necessary for the specific purpose for which it is being shared.'],
  },
  {
    title: '5. DATA RETENTION',
    intro: 'We retain personal information only for as long as reasonably necessary to:',
    bullets: ['Provide the Service', 'Verify and maintain farmer registration', 'Process transactions', 'Maintain transaction records', 'Resolve disputes', 'Maintain system security', 'Comply with applicable legal or regulatory requirements'],
    content: ['Information obtained or referenced from authorized agricultural records will be processed only for legitimate and authorized purposes.', 'When a user closes their account, HarborAI may delete, anonymize, or retain certain information when retention is necessary for legitimate purposes, legal obligations, security, dispute resolution, or record-keeping requirements.', 'Personal information should not be retained longer than necessary for the purpose for which it was obtained, subject to applicable laws and legitimate record-keeping requirements.'],
  },
  {
    title: '6. YOUR RIGHTS',
    intro: 'Subject to the Data Privacy Act of 2012 and applicable regulations, you may have the following rights regarding your personal information:',
    bullets: ['Right to be informed about how your personal information is processed', 'Right to access the personal information HarborAI holds about you', 'Right to correct or rectify inaccurate, incomplete, or outdated information', 'Right to object to certain types of processing, where applicable', 'Right to request erasure or blocking when permitted by law', 'Right to data portability, where applicable', 'Right to withdraw consent when processing is based on consent', 'Right to file a complaint with the National Privacy Commission', 'Right to claim damages for violations of your data privacy rights, subject to applicable law'],
    content: ['If information associated with a farmer\'s registration originates from DA or MAO records, requests concerning the accuracy or correction of the underlying government registration information may need to be coordinated with the appropriate agricultural office.', 'You may exercise your rights by contacting the HarborAI administrator through the privacy contact information provided by the system.'],
  },
  {
    title: '7. SECURITY',
    intro: 'HarborAI implements reasonable and appropriate organizational, physical, and technical security measures to protect personal information against unauthorized access, alteration, disclosure, loss, destruction, or other unlawful processing.',
    bullets: ['Password protection and secure authentication', 'Password hashing', 'Role-based access controls', 'Secure handling of uploaded files and documents', 'Protection of account and transaction information', 'System monitoring and activity logs', 'Regular review of system security', 'Limiting access to personal information to authorized personnel'],
    content: ['While we take reasonable steps to protect your information, no electronic transmission or storage system can be guaranteed to be completely secure.', 'In the event of a personal data breach that requires notification under applicable law, HarborAI will take the necessary steps to investigate, contain, and address the incident and provide notifications to affected parties and appropriate authorities when required.'],
  },
  {
    title: '8. COOKIES AND LOCAL STORAGE',
    intro: 'HarborAI may use browser technologies such as cookies, local storage, and session storage to support essential system functions.',
    bullets: ['Keep users signed in', 'Maintain user sessions', 'Remember system preferences', 'Support cart and order-related functions', 'Improve the functionality and performance of the Service'],
    content: ['HarborAI does not use cookies for unauthorized tracking or the sale of personal information.', 'Users may clear their browser storage or cookies through their browser settings. However, doing so may sign the user out or affect certain HarborAI features.'],
  },
  {
    title: '9. CHILDREN',
    content: ['HarborAI is intended primarily for registered farmers, buyers, institutional buyers, and other users who are legally capable of participating in transactions.', 'We do not knowingly collect personal information from children for purposes that are not appropriate or permitted by applicable law.', 'If you believe that a child has provided personal information to HarborAI without appropriate authorization, please contact the HarborAI administrator so that the information can be reviewed and removed when appropriate.'],
  },
  {
    title: '10. CHANGES TO THIS POLICY',
    content: ['HarborAI may update this Privacy Policy from time to time to reflect changes in the system, applicable laws, regulations, or our data processing practices.', 'When changes are made, the updated Privacy Policy will be posted within the HarborAI system together with a revised effective date.', 'For significant changes, HarborAI may provide additional notice through the Service or other appropriate communication channels.', 'We encourage users to review this Privacy Policy periodically to remain informed about how their personal information is handled.'],
  },
];

export default function PrivacyPolicyPage() {
  return (
    <div className="min-h-screen bg-[#F5F1E5] font-body text-[#123C5C]">
      <style>{`@import url('https://fonts.googleapis.com/css2?family=Anton&family=Baloo+2:wght@400;500;600;700;800&display=swap'); .font-display { font-family: 'Anton', ui-sans-serif, sans-serif; } .font-body { font-family: 'Baloo 2', ui-rounded, system-ui, sans-serif; }`}</style>
      <header className="sticky top-0 z-20 border-b border-[#E7E1D0] bg-[#F5F1E5]/95 shadow-sm backdrop-blur-sm">
        <div className="container mx-auto flex min-h-16 items-center justify-between gap-4 px-4 py-3">
          <Link to="/" className="flex items-center gap-3">
            <img src="/logo.png" alt="HarborAI Logo" className="h-11 w-11 object-contain" />
            <span className="font-display text-xl tracking-tight">HarborAI</span>
          </Link>
          <div className="flex items-center gap-2">
            <Link to="/faq"><Button variant="outline" className="rounded-full border-2 border-[#123C5C] font-bold text-[#123C5C] hover:bg-[#123C5C] hover:text-white">FAQ</Button></Link>
            <Link to="/login"><Button className="rounded-full bg-[#22C55E] font-bold hover:bg-[#15803D]">Login</Button></Link>
          </div>
        </div>
      </header>

      <main className="container mx-auto max-w-5xl px-4 py-8 sm:py-12">
        <section className="mb-8 rounded-2xl border border-[#0F9488]/25 bg-white p-6 shadow-sm sm:p-10">
          <div className="flex flex-col gap-5 sm:flex-row sm:items-start">
            <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-[#0F9488]/15 text-[#0F9488]"><ShieldCheck className="h-7 w-7" /></div>
            <div>
              <p className="text-sm font-bold uppercase tracking-[0.18em] text-[#0F9488]">HarborAI</p>
              <h1 className="mt-1 font-display text-3xl tracking-tight text-[#123C5C] sm:text-5xl">Privacy Policy</h1>
              <p className="mt-3 text-[#45586B]">Effective date: September 15, 2026</p>
              <p className="mt-4 max-w-3xl leading-7 text-[#45586B]">HarborAI values your privacy. This Privacy Policy explains how we process, use, share, store, and protect your information when you use the HarborAI: Smart Trading and Enterprise Platform for Agriculture in Aparri.</p>
              <p className="mt-3 max-w-3xl leading-7 text-[#45586B]">This Privacy Policy is guided by Republic Act No. 10173 (Data Privacy Act of 2012), its Implementing Rules and Regulations, and applicable issuances of the National Privacy Commission (NPC).</p>
            </div>
          </div>
        </section>

        <div className="space-y-6">
          {sections.map((section) => (
            <section key={section.title} className="rounded-2xl border border-[#E7E1D0] bg-white p-6 shadow-sm sm:p-8">
              <h2 className="font-display text-2xl tracking-tight text-[#123C5C] sm:text-3xl">{section.title}</h2>
              {'intro' in section && section.intro && <p className="mt-4 leading-7 text-[#45586B]">{section.intro}</p>}
              {'content' in section && section.content?.map((paragraph) => <p key={paragraph} className="mt-4 leading-7 text-[#45586B]">{paragraph}</p>)}
              {'bullets' in section && section.bullets && <ul className="mt-4 list-disc space-y-2 pl-6 leading-7 text-[#45586B]">{section.bullets.map((bullet) => <li key={bullet}>{bullet}</li>)}</ul>}
              {'subsections' in section && section.subsections && <div className="mt-5 space-y-6">{section.subsections.map(([title, intro, bullets, closing]) => <div key={title}><h3 className="text-lg font-bold text-[#0F9488]">{title}</h3>{intro && <p className="mt-2 leading-7 text-[#45586B]">{intro}</p>}{bullets && <ul className="mt-3 list-disc space-y-2 pl-6 leading-7 text-[#45586B]">{bullets.map((bullet) => <li key={bullet}>{bullet}</li>)}</ul>}{closing && <p className="mt-3 leading-7 text-[#45586B]">{closing}</p>}</div>)}</div>}
            </section>
          ))}

          <section className="rounded-2xl border border-[#0F9488]/30 bg-[#0F9488]/5 p-6 shadow-sm sm:p-8">
            <h2 className="font-display text-2xl tracking-tight text-[#123C5C] sm:text-3xl">11. CONTACT US</h2>
            <p className="mt-4 leading-7 text-[#45586B]">For privacy inquiries, requests concerning your personal information, complaints, or questions regarding this Privacy Policy, please contact the HarborAI System Administrator / Data Privacy Officer through the contact information provided within the HarborAI system.</p>
            <p className="mt-4 leading-7 text-[#45586B]">For concerns regarding farmer registration information originating from the Department of Agriculture or Municipal Agriculture Office, users may also be directed to the appropriate agricultural office when necessary.</p>
            <div className="mt-6 rounded-xl border border-[#E7E1D0] bg-white p-5 leading-8 text-[#45586B]">
              <p className="font-bold text-[#123C5C]">HarborAI</p>
              <p>Smart Trading and Enterprise Platform for Agriculture in Aparri</p>
              <p><strong>Email:</strong> [Insert official HarborAI email]</p>
              <p><strong>Contact Number:</strong> [Insert contact number]</p>
              <p><strong>Address:</strong> Aparri, Cagayan, Philippines</p>
            </div>
            <p className="mt-5 leading-7 text-[#45586B]">For information about your data privacy rights, you may also refer to the <a href="https://privacy.gov.ph/" target="_blank" rel="noreferrer" className="font-bold text-[#0F9488] underline hover:text-[#15803D]">National Privacy Commission</a>.</p>
          </section>
        </div>
      </main>
    </div>
  );
}
