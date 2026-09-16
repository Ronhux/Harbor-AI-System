export type FaqCategoryId =
  | 'general'
  | 'producer'
  | 'buyer'
  | 'orders'
  | 'insights'
  | 'support';

export type FaqItem = {
  question: string;
  answer: string;
};

export type FaqCategory = {
  id: FaqCategoryId;
  label: string;
  description: string;
  items: FaqItem[];
};

export const faqCategories: FaqCategory[] = [
  {
    id: 'general',
    label: 'Pangkalahatan',
    description: 'Pagsisimula sa HarborAI',
    items: [
      {
        question: 'Ano ang HarborAI?',
        answer: 'Ang HarborAI ay isang web platform para sa mga sektor ng agrikultura at pangisdaan sa Aparri, Cagayan. Nagbibigay ito ng product listings para sa mga producer, demand postings at ordering para sa institutional buyers, producer registry tools, at dashboard views para sa market prices at demand.',
      },
      {
        question: 'Sino ang maaaring gumamit ng HarborAI?',
        answer: 'May producer, institutional buyer, at administrator roles ang system. Sa kasalukuyan, producer accounts para sa farmers at fisherfolk ang ginagawa ng public registration screen. Sinusuportahan ng system ang buyer at administrator accounts, ngunit wala silang public self-registration screen.',
      },
      {
        question: 'Ano ang maaari kong gawin sa HarborAI?',
        answer: 'Maaaring gumawa, mag-edit, mag-view, at mag-delete ng product listings ang producers. Maaaring mag-browse ng verified producers, mag-post ng demand notices, mag-place ng orders, at mag-view ng order records ang institutional buyers. Maaaring pamahalaan ng administrators ang producer registry. Nagpapakita rin ang dashboard screens ng market-price at demand information.',
      },
      {
        question: 'Paano ako gagawa ng account?',
        answer: 'Piliin ang Register as Producer sa home page. Ilagay ang iyong pangalan, contact number, email address, password, producer type, at RSBSA number, pagkatapos ay tanggapin ang terms. Awtomatikong mava-verify ang producer kung tugma ang RSBSA number nito sa registry; kung hindi, mananatiling pending ang account para sa review.',
      },
    ],
  },
  {
    id: 'producer',
    label: 'Producer',
    description: 'Para sa farmers at fisherfolk',
    items: [
      {
        question: 'Paano ako magre-register bilang farmer o fisher?',
        answer: 'Buksan ang Register as Producer, kumpletuhin ang kinakailangang personal at account fields, piliin ang Farmer o Fisherfolk, at magbigay ng RSBSA number. Chine-check ng system ang number laban sa producer registry. Ang tumugmang record ay mamamarkahang Verified; kung hindi, magiging pending ang account para sa manual verification.',
      },
      {
        question: 'Ano ang RSBSA?',
        answer: 'Ang RSBSA ay nangangahulugang Registry System for Basic Sectors in Agriculture. Sa HarborAI, ito ang registry number na ginagamit para i-check ang producer record habang nagre-register.',
      },
      {
        question: 'Bakit kailangan ang RSBSA verification?',
        answer: 'Ginagamit ng HarborAI ang RSBSA number para itugma ang producer sa registered-producer records at itakda ang verification status ng account. Ang buyer browsing page ay naglilista ng verified producers.',
      },
      {
        question: 'Paano ako magdadagdag ng product listing?',
        answer: 'Pagkatapos mag-login bilang producer, buksan ang My Listings (Digital Stall) at piliin ang Add New Listing. Ilagay ang product name, category, price per unit, unit, at quantity. Maaari ka ring magdagdag ng description at optional product image. Required ang product name, price, at quantity.',
      },
      {
        question: 'Paano ko ie-edit o aalisin ang product listing ko?',
        answer: 'Sa My Listings, gamitin ang Edit para i-update ang listing at ang Save Changes para i-submit ito. Gamitin ang trash icon para alisin ang listing; hihingi muna ng confirmation ang HarborAI bago ito i-delete.',
      },
      {
        question: 'Paano ko pamamahalaan ang orders?',
        answer: 'Sa kasalukuyan, nagpapakita ang producer Transaction Management screen ng sample order information at details, ngunit hindi pa connected sa backend ang accept at shipping actions nito. Hindi pa available bilang working workflow ang producer order management.',
      },
    ],
  },
  {
    id: 'buyer',
    label: 'Institutional Buyer',
    description: 'Pag-browse ng supply at pagbili',
    items: [
      {
        question: 'Sino ang maaaring mag-register bilang institutional buyer?',
        answer: 'Sinusuportahan ng HarborAI ang Institutional Buyer role, ngunit para sa producers lamang ang kasalukuyang public registration page. Wala pang public registration process para sa institutional buyers sa current interface.',
      },
      {
        question: 'Paano ako makakahanap ng mga produkto?',
        answer: 'Pagkatapos mag-sign in bilang buyer, buksan ang Browse Producers para makita ang verified producers at available listings nila. Ipinapakita ng page ang product, available quantity, price, at location. Hindi pa connected sa filtering ang visible search at filter controls nito.',
      },
      {
        question: 'Paano ako maglalagay ng order?',
        answer: 'Sa Browse Producers, piliin ang Order Now para sa producer listing. Piliin ang product listing, ilagay ang quantity, shipping address, at delivery date, pagkatapos ay i-submit ang Place Order. Chine-check muna ng system kung available ang hinihinging quantity bago gumawa ng order.',
      },
      {
        question: 'Paano ko makikita ang status ng order ko?',
        answer: 'Buksan ang Orders tab sa buyer dashboard. Inililista nito ang iyong orders at maaari mong buksan ang View Details para makita ang ordered items, shipping address, fulfillment status, payment status, at order timeline.',
      },
    ],
  },
  {
    id: 'orders',
    label: 'Orders',
    description: 'Order records at kasalukuyang statuses',
    items: [
      {
        question: 'Ano ang ibig sabihin ng iba’t ibang order statuses?',
        answer: 'Ang mga bagong order na ginawa sa current order API ay naka-set sa Confirmed at ang payment ay marked na Pending. Maaari ring mag-display ang buyer interface ng In Transit at Delivered sa timeline kapag naroon ang mga value na ito. Wala pang producer-side action ang system para baguhin ang order sa pagitan ng mga status na ito.',
      },
      {
        question: 'Paano ko makikita ang order history ko?',
        answer: 'Maaaring buksan ng institutional buyers ang Orders sa kanilang dashboard. Nilo-load ng HarborAI ang orders ng naka-sign-in na buyer at inaayos ang mga ito mula sa pinakabago.',
      },
      {
        question: 'Ano ang nangyayari pagkatapos ma-accept ang order?',
        answer: 'Kapag nag-place ng order ang buyer, ginagawa ito ng HarborAI bilang Confirmed, sine-set ang payment sa Pending, nire-record ang ordered listing at quantity, at binabawasan ang available quantity ng listing. Wala pang working workflow ang current system para sa producer acceptance, payment, shipping, o delivery updates.',
      },
    ],
  },
  {
    id: 'insights',
    label: 'Market Insights & AI',
    description: 'Pag-unawa sa dashboard information',
    items: [
      {
        question: 'Ano ang Market Insights feature ng HarborAI?',
        answer: 'Kasama sa producer dashboard ang Market Insights at Smart Pricing Prompts screens. Nagpapakita ang mga ito ng example enterprise opportunities, price-trend charts, demand charts, market averages, at pricing prompts. May Price Trends screen din ang buyers.',
      },
      {
        question: 'Paano nagbibigay ang HarborAI ng market insights?',
        answer: 'Sa current version, nagpapakita ang market-insight at price-trend screens ng data na naka-define sa frontend. Nakalagay sa refresh, save, action-plan, at full-analysis controls na hindi pa implemented ang mga ito, kaya hindi pa kumukuha o gumagawa ang system ng live AI analysis.',
      },
      {
        question: 'Anong mga factor ang maaaring makaapekto sa product prices?',
        answer: 'Inihahambing ng displayed screens ang current price ng producer sa market average at ipinapakita ang price trends, demand levels, seasonal opportunities, at institutional buyer activity. Hindi pa nagdo-document o nagca-calculate ang HarborAI ng mas malawak na set ng price factors.',
      },
      {
        question: 'Nagrerekomenda ba ang HarborAI ng eksaktong selling price?',
        answer: 'Hindi. Nagpapakita ang current Smart Pricing Prompts screen ng example price comparisons at question-style guidance. Hindi ito nagbibigay ng live at eksaktong selling-price recommendation.',
      },
      {
        question: 'Maaari bang hulaan ng HarborAI ang future market prices?',
        answer: 'May displayed trend at demand-projection charts ang dashboard, ngunit wala pang implemented live forecasting service o guarantee ng future prices. Gamitin lamang ang information bilang planning context.',
      },
      {
        question: 'Paano maaaring makaapekto sa prices ang inflation at fuel costs?',
        answer: 'Hindi ipinapakita o kino-compute ng current HarborAI market screens ang inflation o fuel-cost data. Sa pangkalahatan, maaaring makaapekto ang mga gastusing ito sa production at transport expenses, ngunit hindi pa sinusukat ng HarborAI ang epekto nito.',
      },
      {
        question: 'Dapat ba akong umasa lamang sa AI-generated insights ng HarborAI?',
        answer: 'Hindi. Informational lamang ang current insight screens at may static example data ang mga ito. Isaalang-alang ang actual market conditions, iyong costs, buyer requirements, at verified information bago gumawa ng pricing o production decision.',
      },
    ],
  },
  {
    id: 'support',
    label: 'Support',
    description: 'Tulong sa Login at troubleshooting',
    items: [
      {
        question: 'Ano ang dapat kong gawin kung nakalimutan ko ang password ko?',
        answer: 'Wala pang password-reset o recovery feature ang HarborAI. Kailangang i-configure ng system administrator ang contact information para sa account recovery.',
      },
      {
        question: 'Bakit hindi ako maka-login?',
        answer: 'Tingnan kung ginagamit mo ang email address at password na ginamit sa account registration. Nagpapakita ang login form ng invalid credentials kapag hindi tugma ang mga ito. Maaaring kailanganin din ng verification ang producers na pending pa ang registration bago magkaroon ng full access.',
      },
      {
        question: 'Ano ang dapat kong gawin kung magkaroon ng system error?',
        answer: 'Subukang i-refresh ang page at tingnan ang iyong internet connection. Kung magpatuloy ang problema, itala kung ano ang ginagawa mo at ang anumang message na lumabas sa screen, pagkatapos ay ibahagi ito sa system administrator kapag available na ang contact details.',
      },
      {
        question: 'Paano ko makokontak ang HarborAI administrator?',
        answer: 'Hindi pa nagbibigay ang current system ng administrator contact details o working support-contact feature. Kailangang mag-configure ang system administrator ng official contact method.',
      },
    ],
  },
];
