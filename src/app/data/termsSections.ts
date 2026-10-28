/**
 * HarborAI Terms and Conditions content.
 *
 * NOTE ON PROVENANCE: the exact wording of the originally supplied 21-section
 * Terms and Conditions was not preserved anywhere in this repository (no copy
 * exists in `docs/`, `guidelines/`, or git history). The sections below are
 * written to match the behavior that is actually implemented and tested in
 * this codebase, and are organized as the 21 sections the HarborAI Terms page
 * is required to carry. If the original approved wording is available, it
 * should replace this file verbatim and the version in `config/policies.php`
 * should be bumped so previously accepted accounts re-review it.
 */

export type TermsSection = {
  title: string;
  paragraphs: string[];
  bullets?: string[];
};

export const TERMS_VERSION = '2026-10-08';

export const termsSections: TermsSection[] = [
  {
    title: 'Acceptance of These Terms',
    paragraphs: [
      'These Terms and Conditions govern your use of HarborAI, the agricultural trading and enterprise platform operated for the Municipality of Aparri, Province of Cagayan. By creating a HarborAI account you agree to these Terms and to the HarborAI Privacy Policy.',
      'If you do not agree with these Terms, do not create an account. If you use HarborAI on behalf of an organization, you confirm that you are authorized to accept these Terms for that organization.',
    ],
  },
  {
    title: 'Who Can Use HarborAI',
    paragraphs: [
      'HarborAI accounts are issued to agricultural producers (farmers and fisherfolk), institutional buyers, and authorized administrators.',
      'Producer accounts are created through the public registration page and require a registry match against the official registered producer dataset. Institutional buyer and administrator accounts are issued by the responsible DA/LGU office; they are not self-service.',
    ],
  },
  {
    title: 'Account Requirements',
    paragraphs: [
      'You must provide a valid Philippine mobile number when registering. Your mobile number is your primary HarborAI sign-in identifier. An email address is optional; if you provide one it must be a working address you control.',
      'You are responsible for keeping your sign-in credentials confidential and for all activity performed under your account. Notify the HarborAI operator promptly if you believe your credentials have been misused.',
    ],
  },
  {
    title: 'Registry Verification',
    paragraphs: [
      'When you register as a producer, HarborAI checks the identity information you submit against the registered producer dataset for your RSBSA number, including your name, barangay, and sex as listed in the registry.',
      'Registry-backed fields on your producer profile are authoritative and are not editable from your profile page. HarborAI does not create an account when the submitted identity does not match the registry record.',
    ],
  },
  {
    title: 'Your HarborAI Account',
    paragraphs: [
      'You are responsible for the accuracy of the information you submit, including your mobile number, email address, product listings, demand requests, and any description of goods or quantities.',
      'Do not submit information in a listing or request that should not be shared with its intended audience. HarborAI surfaces producer and product information to users browsing the marketplace.',
    ],
  },
  {
    title: 'Product Listings',
    paragraphs: [
      'Producers may publish listings describing agricultural or fishery products available for institutional buyers, including product name, category, price, unit, available quantity, description, location, and harvest or expiry dates.',
      'A listing is only visible to buyers when it is active and available. HarborAI does not verify the accuracy of a listing, does not act as a party to any sale, and does not guarantee that a listing will result in a transaction.',
    ],
  },
  {
    title: 'Demand Requests',
    paragraphs: [
      'Institutional buyers may post demand requests describing a product, requested quantity, unit, target price, location, and deadline, and may respond to offers submitted by producers.',
      'A posted request is a statement of institutional interest. It is not a binding purchase commitment, an award, or a procurement decision.',
    ],
  },
  {
    title: 'Offers and Negotiations',
    paragraphs: [
      'Producers may submit offers in response to a demand request, and both parties may revise, accept, or decline an offer through the HarborAI negotiation workflow.',
      'An offer becomes an order only when both parties confirm the same agreed version of the terms. HarborAI records the confirmed terms, including any separately agreed charges, and shows them to the parties involved.',
    ],
  },
  {
    title: 'Orders and Fulfillment',
    paragraphs: [
      'Orders in HarborAI record the parties, the products and quantities ordered, the recorded amount, fulfillment status, payment status, delivery information, and shipping address.',
      'HarborAI tracks and displays order status. It does not collect payment, does not guarantee delivery, and does not resolve disputes between the parties.',
    ],
  },
  {
    title: 'Administrative Reports',
    paragraphs: [
      'Authorized administrators may generate, save, and export HarborAI reports covering producer activity, supply and demand, buyer demand, and order transactions.',
      'Administrative reports contain system-generated figures from HarborAI records as of the report timestamp. Reported order amounts are order values; they are not realized revenue, income, or profit, and report figures should be verified before being distributed or relied upon externally.',
    ],
  },
  {
    title: 'Market Data and Reference Information',
    paragraphs: [
      'HarborAI may present reference price, seasonality, and supply information from official or verified sources, and clearly labels the source, period, and geographic scope of that information.',
      'Reference and market information is historical context. It is not a live quote, a guaranteed price, or an offer, and it is specific to the source geography and period shown.',
    ],
  },
  {
    title: 'AI-Assisted Features',
    paragraphs: [
      'HarborAI may offer AI-assisted analysis features. When the configured AI service is unavailable, HarborAI falls back to deterministic analysis computed from stored records and clearly indicates that AI interpretation is unavailable.',
      'AI-assisted output is informational. You remain responsible for verifying information before relying on it, and HarborAI does not use AI features to change a listing price, publish a listing, or place an order on your behalf.',
    ],
  },
  {
    title: 'Acceptable Use and Prohibited Content',
    paragraphs: [
      'You must not use HarborAI to post unlawful, fraudulent, misleading, or infringing content; to impersonate another person or organization; to attempt to access another account; to circumvent the registry verification process; to interfere with the operation of the platform; or to harvest, scrape, or resend other users’ personal information.',
      'Listings, requests, and messages must not contain prohibited agricultural or fishery products, must comply with applicable laws, permits, and sanitary or phytosanitary requirements, and must not misrepresent a product’s origin, grade, quantity, or certification status.',
      'Automated access to HarborAI other than through the interfaces provided by the platform is not permitted.',
    ],
  },
  {
    title: 'Intellectual Property',
    paragraphs: [
      'HarborAI, its name, logo, interface, report layouts, and software are owned by their respective rights holders and may not be copied, reproduced, or redistributed without permission.',
      'You retain ownership of content you submit. You grant HarborAI the limited right to host, display, and process that content only as necessary to operate the marketplace and the features you use.',
    ],
  },
  {
    title: 'Confidentiality',
    paragraphs: [
      'Each party must keep confidential information received through a negotiation, order, or administrative report and may use it only for the purpose for which it was shared.',
      'Confidentiality obligations do not apply to information that is already public, independently developed, or required to be disclosed by law.',
    ],
  },
  {
    title: 'Privacy and Personal Data',
    paragraphs: [
      'HarborAI processes account, profile, listing, request, order, and acceptance information as described in the HarborAI Privacy Policy. Read that policy before relying on how your information is handled.',
      'Verifying the accuracy of submitted identity information against the registered producer dataset is a necessary part of producer registration and account operation.',
    ],
  },
  {
    title: 'Acceptance of Policies and Account Activation',
    paragraphs: [
      'Creating an account requires an affirmative agreement to these Terms and an acknowledgment that you have read the Privacy Policy. HarborAI records the policy version you accepted and the server-side date and time of that acceptance.',
      'An account that has not recorded acceptance of the current policies cannot use protected HarborAI features. If the policies are updated, you will be asked to review and accept the current version before continuing.',
    ],
  },
  {
    title: 'Suspension, Deactivation, and Termination',
    paragraphs: [
      'An authorized administrator may suspend or deactivate an account, for example where account information is inaccurate, where a registry or policy requirement is not met, or where the Terms are not followed.',
      'You may request deactivation of your account by contacting the HarborAI operator. On termination, access to the account ends, and retained records are handled as described in the HarborAI Privacy Policy.',
    ],
  },
  {
    title: 'Disclaimers',
    paragraphs: [
      'HarborAI is provided on an "as available" basis. To the extent permitted by law, HarborAI disclaims implied warranties of merchantability, fitness for a particular purpose, and uninterrupted availability.',
      'Harvest, weather, market, price, and regulatory conditions are outside HarborAI’s control. HarborAI does not guarantee crop yields, prices, order completion, or the availability of any product, request, or feature.',
    ],
  },
  {
    title: 'Limitation of Liability',
    paragraphs: [
      'To the extent permitted by law, HarborAI is not liable for indirect, incidental, special, consequential, or exemplary damages, or for lost profits or lost agricultural output, arising from use of the platform or from any transaction between users.',
      'Where liability cannot be excluded, it is limited to the amount you actually paid to use HarborAI in the twelve months before the event giving rise to the claim.',
    ],
  },
  {
    title: 'Changes to These Terms and Contact',
    paragraphs: [
      'These Terms may be updated as HarborAI’s features, data fields, or operating practices change. The version number and updated date appear on this page; when the version changes you will be asked to accept the current version again.',
      'Questions about these Terms, about your account, or about HarborAI in general can be directed to the HarborAI operator through the project contact channel.',
    ],
  },
];