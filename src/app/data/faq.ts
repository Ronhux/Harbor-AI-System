export type FaqCategoryId =
  | 'general'
  | 'registration'
  | 'kadiwa'
  | 'fca'
  | 'buyer'
  | 'orders'
  | 'insights'
  | 'privacy'
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
    description: 'Kung ano ang HarborAI at sino ang maaaring gumamit nito',
    items: [
      {
        question: 'Ano ang HarborAI?',
        answer: 'Ang HarborAI ay isang web-based agricultural trading at decision-support platform para sa Aparri, Cagayan. Tinutulungan nitong mag-organisa ng agricultural product listings, buyer demand, negotiations, transactions, at market-reference information. Mayroon din itong handin na mga gawi para sa KADIWA, FCA verification, at institutional supply coordination, ngunit hindi pa ito aktibong available sa mga gumagamit.',
      },
      {
        question: 'Sino ang maaaring gumamit ng HarborAI?',
        answer: 'Ang mga pangunahing gumagamit ay agricultural producers, institutional buyers, system administrators, at Super Admin. May nakalaang mga espesyal na tungkulin para sa mga awtorisadong FCA representative at KADIWA coordinator, kapag nakatulad ang mga workflow na iyon. Magkakaiba ang access at pahintulot ng bawat account base sa uri at status nito.',
      },
      {
        question: 'Para lamang ba sa mga RSBSA-registered farmers ang HarborAI?',
        answer: 'Ang kasalukuyang Producer registration ay para sa RSBSA-registered agricultural farmers na kailangang sumailalim sa registry matching at hiwalay na Admin approval. Ang suporta para sa mga farmer na wala pang RSBSA record ay hindi kabilang sa kasalukuyang release at maaaring pag-aralan sa mga susunod na bersyon. Hindi rin awtomatikong kahulugan ng pagkakaroon ng HarborAI account ang pagiging kwalipikado sa anumang programa ng pamahalaan.',
      },
      {
        question: 'Sakop ba ng HarborAI ang fisheries at livestock?',
        answer: 'Ang kasalukuyang inaprubahan na saklaw ng HarborAI ay mga pananim at iba pang hilaw na produktong agrikultura gaya ng gulay at prutas. Hindi kasama sa pangunahing trading scope ang mga produktong pangisdaan, sakay ng hayop, at mga produktong pinroseso. Kung may produkto kang labag sa saklaw na ito, makipag-ugnayan muna sa isang awtorisadong administrator.',
      },
      {
        question: 'Official government system ba ang HarborAI?',
        answer: 'Ang HarborAI ay capstone-developed na platform na inihanda upang umayon sa ilang agricultural trading procedure at government-supported initiative. Hindi ito awtomatikong opisyal o accredited na government system. Ang aktuwal na paggamit ng opisyal na government record at ng pangh_impormasyon ay nangangailangan pa ng tamang awtorisasyon at ng pahintulot ng responsible na ahensiya.',
      },
    ],
  },
  {
    id: 'registration',
    label: 'Farmer at Account',
    description: 'Pagpaparehistro, beripikasyon, at listing',
    items: [
      {
        question: 'Paano gumawa ng Farmer account?',
        answer: 'Sa public registration, kailangan ng iyong pangalan, sex, barangay sa Aparri, mobile number, producer type, at isang wastong RSBSA number na tumutugma sa registry record. Opsyonal ang email address. Pagkatapos ng registry match, pipiliin mo ang iyong password at tanggapin ang mga tuntunin. Ang mobile number ay kailangan, habang ang email ay maaaring pumalya at punan mo na lamang sa profile kapag gusto mong makatanggap ng abiso.',
      },
      {
        question: 'Ano ang RSBSA?',
        answer: 'Ang RSBSA ay Registry System for Basic Sectors in Agriculture. Ginagamit ito ng HarborAI bilang bahagi ng pagtutuos sa inilagay mong registration details, ngunit hindi nito awtomatikong napapatunayan ang pagkakakilanlan ng mismong tao na gumagawa ng account. Ang pagtugma lamang sa registry ay hindi sapat bilang ganap na patunay ng pagkakakilanlan.',
      },
      {
        question: 'Kapag matched ang RSBSA ko, approved na ba agad ang account ko?',
        answer: 'Hindi. Magkaiba ang registry matching at final na account approval. Kahit tumugma ang iyong registration sa registry, maaari pa ring manatiling Pending ang account hanggang masuri ito at aprubahan ng awtorisadong administrator. Ito ang paraan upang maiwasan ang maling paggamit ng ibang tao sa rehistro.',
      },
      {
        question: 'Ano ang ibig sabihin ng Pending, Verified, at Rejected?',
        answer: 'Ang Pending ay naghihintay pa ng pagsusuri ng administrator. Ang Verified ay nangangahulugang aprubado na ang account at maaari nang magamit ang mga protektadong Producer function. Ang Rejected ay nangangahulugang hindi naaprubahan ang aplikasyon at maaari itong suriin o ipaulit muli. Hindi ito katumbas ng registry match, dahil hiwalay ang pagpapasya sa rehistro at sa account.',
      },
      {
        question: 'Puwede bang mag-register kung wala pa akong RSBSA number?',
        answer: 'Hindi sa kasalukuyang release. Ang Producer registration ay para sa RSBSA-registered agricultural farmers lamang, dahil kailangan nito ng registry matching at hiwalay na Admin approval. Ang suporta para sa mga farmer na wala pang RSBSA record ay maaaring pag-aralan sa mga susunod na bersyon.',
      },
      {
        question: 'Paano ako gagawa ng product listing ko?',
        answer: 'Kapag naaprubahan na ang iyong account, buksan ang My Digital Stall sa iyong Producer dashboard at piliin ang Add New Listing. Ilagay ang pangalan ng produkto, kategorya, presyo kada yunit, yunit ng sukat, at dami ng available stock. Maaari kang magdagdag ng maikling paglalarawan at ng produkto o larawan ng iyong listing. Ang ganitong protektadong pag-andito ay kailangan ng naaprubahan at aktibong account.',
      },
      {
        question: 'Puwede bang gamitin ng ibang tao ang RSBSA number ko?',
        answer: 'Hindi dapat gamitin ng ibang tao ang iyong registration information para magpanggap na ikaw. May registry matching at hiwalay na Admin approval ang HarborAI, ngunit hindi lubos na mapipigilan ng registry match lamang ang pagpapansin sa pagkakakilanlan. Kung may pinaghihinala kang maling paggamit ng iyong datos, agad itong ipaalam sa isang awtorisadong administrator.',
      },
    ],
  },
  {
    id: 'kadiwa',
    label: 'KADIWA at MAO',
    description: 'Direktang pagbenta at tulong sa MAO',
    items: [
      {
        question: 'Ano ang KADIWA?',
        answer: 'Ang KADIWA ay isang government-supported na direktang pagbenta na nagbibigay sa mga producer ng pagkakataong makipagbenta nang direkta sa mga kostumer. Hindi nangangahulugan ng pakikilahok sa KADIWA na ang lokal na pamahalaan mismo ang bumibili ng lahat ng produkto.',
      },
      {
        question: 'Sino ang dapat makakita ng KADIWA page?',
        answer: 'Ang KADIWA access sa HarborAI ay para sa mga RSBSA-registered at Admin-approved na farmer. Ang account para sa mga farmer na wala pang RSBSA record ay hindi kabilang sa kasalukuyang release. Sa ngayon, hindi pa aktibo ang mismong KADIWA page at API sa sistema, kaya wala pang maaaring gamitin.',
      },
      {
        question: 'Kailan ginaganap ang KADIWA sa Aparri?',
        answer: 'Batay sa konsultasyon sa MAO, ang regular na sanggunian ng iskedyul ay ang ikatlong Lunes at ang kasunod na Martes ng bawat buwan. Ang mga ipinapakitang petsa ay projection lamang at hindi awtomatikong nakumpirmang event. Sundin lamang ang mga petsa at detalye na opisyal na na-publish o kinumpirma ng isang awtorisadong coordinator.',
      },
      {
        question: 'Paano mag-apply sa KADIWA event?',
        answer: 'Sa kasalukuyan, wala pang bukas na aplikasyon para sa KADIWA event. Hindi pa aktibo ang mga workflow na iyon sa sistema, kaya wala pang maaaring sulatan na registration o application form para sa isang event. Sundin ang inilabas na anunsyo kapag nagsimula na ang mga ito.',
      },
      {
        question: 'Pareho ba ang tanong sa tulong ng MAO at aplikasyon sa KADIWA?',
        answer: 'Hindi. Ang tanong sa tulong ng MAO ay para lamang sa paghingi ng impormasyon o pagngumit nang gabay, samantalang ang aplikasyon sa KADIWA ay para sa paggamit ng isang partikular na event. Ang tanong ay hindi awtomatikong nagiging aprubadong paggamit sa isang event.',
      },
      {
        question: 'Kapag nag-apply ako sa KADIWA, may slot na ba ako agad?',
        answer: 'Hindi. Ang pakikilahok ay nakadepende sa aktuwal na availability ng event, sa mga kinakailangan, at sa pagsusuri ng awtorisadong tauhan. Hindi garantiya ng slot o aprubasyon ang pagpapadala ng aplikasyon o pagtatanong lamang.',
      },
    ],
  },
  {
    id: 'fca',
    label: 'FCA at Sagip Saka',
    description: 'Kooperatiba, awtoridad, at institutional access',
    items: [
      {
        question: 'Ano ang FCA?',
        answer: 'Ang FCA ay Farmers Cooperative o Association. Sa HarborAI, ang organisasyon at ang awtoridad ng kaukulang tao ay hinihiwalay at sinusuri nang isa-isa, para hindi maling mapaunawa na kasalitan ng pagiging miyembro at pagiging kinatawan.',
      },
      {
        question: 'Pareho ba ang registered farmer at FCA President?',
        answer: 'Hindi. Ang registered farmer ay isang indibidwal na producer na may sariling produkto at account. Ang FCA President o kinatawan naman ay nag-aangal ng karapatang makipag acting para sa isang buong organisasyon, kaya kailangan nito ng hiwalay na beripikasyon at awtorisasyon bago pa siya makapagpasiya nang libre.',
      },
      {
        question: 'Puwede ba agad gamitin ang FCA account kapag nasa directory?',
        answer: 'Hindi sapat ang pagiging nakalista sa directory bilang patunay na awtorisadong kumatawan ng isang FCA. Kailangan pa ng karagdagang pagsusuri ng contact o ebidensiya bago maisagawa ang anumang gawi para sa organisasyon. Kasalukuyan, hindi pa aktibo ang mga FCA workflow sa HarborAI, kaya wala pang naa-access na FCA President registration o organisasyonal na pagbebenta para sa pampublikong gamit.',
      },
      {
        question: 'Ano ang Sagip Saka-related na feature ng HarborAI?',
        answer: 'Nakatuon ang mga Sagip Saka-related na gawi sa impormasyon at sa koordinasyon ng institutional demand at supply para sa angkop na agrikultural na oportunidad. Hindi awtomatikong ibinibigay ng HarborAI ang opisyal na accreditation, procurement eligibility, pagkakaroon ng kontrata sa pamahalaan, o bayad mula sa gobyerno.',
      },
      {
        question: 'Puwede ba akong direktang magbenta sa LGU gamit ang HarborAI?',
        answer: 'Maaaring makatulong ang HarborAI sa pag-koordinasyon ng institutional demand, supply offer, at kasunduan sa pagitan ng mga kalahok. Gayunman, ang aktuwal na pamimili ng pamahalaan ay dapat dumaan sa naaangkop na procurement procedure at awtorisasyon. Ang posting ng demand o probisyonal na allocation sa HarborAI ay hindi katumbas ng opisyal na kontrata.',
      },
    ],
  },
  {
    id: 'buyer',
    label: 'Institutional Buyer',
    description: 'Hanap ng produkto, demand, at negotiation',
    items: [
      {
        question: 'Sino ang Institutional Buyer?',
        answer: 'Ang Institutional Buyer ay isang awtorisadong institutional account na maaaring tumingin ng agricultural product, mag-post ng demand, at makipag-ugnayan sa mga producer sa loob ng mga pinapahintulutang function ng sistema. Hindi ito isang bagay na awtomatikong nakukuha sa pagpaparehistro.',
      },
      {
        question: 'Paano nagkakaroon ng Buyer account?',
        answer: 'Hindi may simpleng public self-registration para sa Institutional Buyer sa kasalukuyan. Ang public registration screen ay para lamang sa producer ang inaalok. Ang mga buyer account ay inihahanda at pinapag-aaprubahan ng isang awtorisadong administrator batay sa kaniyang patakaran at organisasyon, kaya makipag-ugnayan muna sa iyong administrator kung kailangan nito.',
      },
      {
        question: 'Paano makakahanap ng agricultural product?',
        answer: 'Kapag mayroon kang buyer account, maaari mong buksan ang Browse Products at Browse Products para makita ang mga listing at ang producer sa kanila. Available doon ang paghahanap at pagsala ayon sa salitang hinahanap, kategorya, presyo, lokasyon, at producer, at maaari mo ring ayusin ang pagkakasunod-sunod ng resulta.',
      },
      {
        question: 'Paano mag-post ng demand?',
        answer: 'Buksan ang Post Demand sa iyong Buyer dashboard at ilagay ang produkto o commodity na kailangan, ang dami, ang yunit ng sukat, at, kung mayroon, ang maximum na gusto mong bayarin kada yunit at ang target na petsa. Isang produkto lamang ang tinutukoy ng bawat isang demand sa kasalukuyang bersyon, kaya maghiwalay ka ng maraming posting kung kailangan mo ng iba’t ibang produkto.',
      },
      {
        question: 'Paano tumutugon ang producer sa buyer demand?',
        answer: 'Makikita ng mga producer ang mga bukas na demand sa kanilang Demand Opportunities. Maaari silang magpadala ng supply offer na may sariling quantity at presyo, na maaaring iba sa mismong hinihingi ng buyer, at maaaring i-edit o i-withdraw ang kanilang offer habang hindi pa tumatanggap ang buyer.',
      },
      {
        question: 'Automatic bang nagiging order kapag may tumugon sa demand ko?',
        answer: 'Hindi. Ang pag-post ng demand o pagpapadala ng supply offer ay hindi agad nangangahulugan ng kumpletong order. Maaaring magkaroon ng negotiation at kasunduan muna bago magkaroon ng transaction record, at kailangan pa ring kumpirmahin ng buyer ang mga detalye bago ito maisusulat bilang order.',
      },
    ],
  },
  {
    id: 'orders',
    label: 'Orders at Transactions',
    description: 'Fulfillment, status, at record ng transaksyon',
    items: [
      {
        question: 'Paano nabubuo ang order?',
        answer: 'Ang kasalukuyang paraan ng paggawa ng order ay mula sa isang nakumpirmang negosasyon. Kapag napagkasunduan na ng buyer at producer ang produkto, dami, at presyo, maaari nang gawin ng buyer ang order mula sa negotiation screen at ilagay ang shipping address. Hindi awtomatikong nagagawa ang order kagahit lamang may tumugon o napagkasunduan.',
      },
      {
        question: 'Ano ang mga fulfillment status?',
        answer: 'Maaaring dumaan ang isang order sa mga hakbang na Order Submitted, Order Confirmed, Preparing Order, Ready for Shipment, Shipped, Delivered, at Completed. Magkakaiba ang ipinapahintulot ng producer at ng buyer sa bawat hakbang, kaya hindi lahat ng status ang maaaring itakda ninuman sa kahit anong pagkakataon.',
      },
      {
        question: 'Sino ang maaaring mag-update ng status ng order?',
        answer: 'Ang producer ang maaaring mag-update ng fulfillment status ng order ayon sa itinakdang proseso. Sa Buyer Orders page, maaari mong tingnan ang status at detalye ng iyong mga order at gamitin ang Ongoing at Completed filters. Sa kasalukuyan, wala pang button sa Buyer interface para direktang mag-confirm, mag-complete, o mag-cancel ng order.',
      },
      {
        question: 'Puwede bang bawiin ang isang demand o order?',
        answer: 'Maaaring kanselahin ng buyer ang isang demand kung pinapayagan pa ito ng kasalukuyang status. Maaari ring bawiin ng producer ang isang offer na hindi pa tinatanggap. Para sa mismong order, wala pang cancellation button sa Buyer Orders page. Kung may problema sa order, makipag-ugnayan sa producer o sa available na support channel.',
      },
      {
        question: 'Paano ko makikita ang transaction history ko?',
        answer: 'Ang mga buyer ay may Orders section sa kanilang dashboard, habang ang mga producer ay may Transaction Management section sa kanilang Producer dashboard. Nakikita rito ang mga aktuwal at nakatapos nang transaksyon, kasama ang estado ng fulfillment at payment, at nananatili ang mga ito bilang record kahit na lumipat na sa final na estado.',
      },
      {
        question: 'May online payment ba ang HarborAI?',
        answer: 'Wala pang nakalakip na online payment gateway ang HarborAI. Maaaring magpakita lamang ang sistema ng payment status para sa tulong sa koordinasyon, at nangangahulugang hindi nito awtomatikong nagpoproseso o nagkukumpirma ng totoong bayad. Ang pagbabago ng fulfillment status ay hindi rin nangangahulugang natapos na ang bayad.',
      },
      {
        question: 'Paano hinahawakan ang kasalukuyang stock at hinaharap na ani?',
        answer: 'Inihiwalay ng HarborAI ang available na stock at ang hinaharap na supply batay sa detalye ng listing at availability. Ang order para sa hinaharap na ani ay hindi direktang kumukuha ng dami mula sa kasalukuyang inventory mo. Kailangan pa ring kumpirmahin ng mga kalahok ang totoong availability at ang aktuwal na fulfillment.',
      },
    ],
  },
  {
    id: 'insights',
    label: 'Market Insights at AI',
    description: 'Reference data at pricing support',
    items: [
      {
        question: 'Ano ang Smart Pricing Prompts?',
        answer: 'Ang Smart Pricing Prompts ay feature na sumusuporta sa pagpapasya na naghahambing ng iminumungkahing presyo ng benta mo sa mga available at katugmang historical reference observation. Nagbibigay ito ng impormasyon at gabay na tanong, ngunit hindi nito awtomatikong itinatakda ang presyo ng iyong produkto.',
      },
      {
        question: 'Ano ang Market Insights?',
        answer: 'Nagbibigay ang Market Insights ng impormasyon mula sa mga available na reference observation at kaugnay na agricultural market data, kasama ang iyong sariling listing at naitalang supply at demand. Nakadepende ang ipinapakita sa aktuwal na saklaw, petsa, pinagmulan, at pagkatugma ng available na datos sa iyong produkto.',
      },
      {
        question: 'Official PSA data ba lahat ng presyong ipinapakita?',
        answer: 'Hindi lahat ng reference observation ay opisyal na beripikadong PSA data. Maaaring kasama rito ang mga historical reference, naisrang kalkulasyon, at ilang synthetic o pagsubok na tala. Dahil dito, laging tingnan ang source at ang label ng isang datos bago gamitin ang impormasyon sa isang desisyon sa negosyo.',
      },
      {
        question: 'Paano kung may presyong ₱0 na observation?',
        answer: 'Ang mga zero-valued na observation na walang malinaw na paliwanag ay hindi isinasama sa comparable na statistics ng presyo ng HarborAI. Hindi ito nabubura sa tala, ngunit pinipigilan itong makisira sa kalkulasyon ng katamtaman o range upang hindi malabo ang resulta sa iyon.',
      },
      {
        question: 'Paano kung walang datos para sa produkto ko?',
        answer: 'Ipapakita ng HarborAI na walang sapat o katugmang reference observation para sa iyong produkto. Sa ganito, dapat kang mag-ingat at huwag umasa sa imumungkahing presyo na walang batayan. Mas mainam tingnan ang sariling gastos, aktuwal na merkado, at pangangailangan ng buyer bago magpasya.',
      },
      {
        question: 'Kayang hulaan ba ng AI ang future selling price?',
        answer: 'Hindi ginagarantiya ng HarborAI ang future selling price o ang magiging kita ng isang producer. Ang kasalukuyang tampok ay gumagamit ng available na reference observation at paliwanag na sumuuportat lamang, at hindi ng independently beripikadong predictive forecasting. Ang aktuwal na presyo ay desisyon mo pa rin.',
      },
      {
        question: 'Ano ang mangyayari kung unavailable ang AI service?',
        answer: 'Maaaring patuloy na gumana ang deterministic market-reference analysis kahit wala nang makapagbigay ng AI-generated na paliwanag. Nananatili sa HarborAI ang pagpapakita ng buong reference analysis, at hindi dapat gumawa ng sistema ng kathang-isip na paliwanag o market data kapag may pagkabigo mula sa provider.',
      },
      {
        question: 'Dapat ba akong umasa lamang sa HarborAI sa pagtakda ng presyo?',
        answer: 'Hindi. Isaalang-alang ang sarili mong gastos sa produksyon, ang aktuwal na kalagayan ng lokal na merkado, ang gastos ng transport, ang kailangan ng buyer, at ang maaasahang reference information. Ang final na presyo ng benta ay nananatiling desisyon ng producer.',
      },
    ],
  },
  {
    id: 'privacy',
    label: 'Privacy at Security',
    description: 'Pagkaprotekta ng datos at pagkakakilanlan',
    items: [
      {
        question: 'Paano pinoprotektahan ng HarborAI ang personal na impormasyon?',
        answer: 'Gumagamit ang HarborAI ng authentication, role-based na access restriction, hiwalay na admin approval, at iba pang security control upang limitahan ang paggamit ng mga protektadong function. Gayunman, ang paggamit ng tunay na government registry record sa aktuwal na operasyon ay nangangailangan pa ng tamang awtorisasyon at ng karagdagang proteksyon sa privacy at pagpapatakbo.',
      },
      {
        question: 'Official na government registry ba ang ginamit ngayon?',
        answer: 'Sa kasalukuyang pagsubok ng capstone, synthetic na registry record ang ginamit upang ma-verify ang pag-andito ng sistema nang hindi ginagamit ang tunay na personal na datos ng mga magsasaka. Ang paggamit ng opisyal na impormasyon mula sa government registry ay nakadepende sa legal na access at awtorisasyon ng responsible na ahensiya.',
      },
      {
        question: 'Bakit kailangan pa rin ng admin approval kahit tumugma na ang RSBSA?',
        answer: 'Ang registry match ay nangangahulugang may katugmang reference na impormasyon lamang, at hindi nito awtomatikong napapatunayan ang pagkakakilanlan ng nagrehistro o gumamit ng account. Kaya may hiwalay na proseso ng admin approval bago ibigay ang protektadong access sa account.',
      },
      {
        question: 'Ano ang gagawin kung may gumagamit ng aking RSBSA nang hindi ako?',
        answer: 'Iulat agad ang pinaghihinalaang pagpansin sa pagkakakilanlan sa isang awtorisadong administrator sa pamamagitan ng available na official support channel. May account suspension at administrative audit ang HarborAI, ngunit hindi pa lubos na nasasagot ng sistema ang proseso ng pagsusuri at pagbawi ng account, kaya kailangan pa itong suportahan ng karagdagang beripikasyon.',
      },
      {
        question: 'May SMS OTP verification ba ang HarborAI?',
        answer: 'Wala pang live na SMS o OTP identity verification sa kasalukuyang sistema. Ang umiiral na proseso ng account approval ay gumagamit ng registry matching at pagsusuri ng administrator, at hindi ito dapat ilarawan bilang SMS-verified na pagpapatunay ng pagkakakilanlan. Maaaring idagdag ang SMS verification sa hinaharap kapag napagpasyahan at naayos ang mga kinakailangang serbisyo.',
      },
    ],
  },
  {
    id: 'support',
    label: 'Support',
    description: 'Pag-login at pag-aayos ng problema',
    items: [
      {
        question: 'Bakit hindi ako maka-login?',
        answer: 'Tiyaking tama ang mobile number o email address na ginamit mo sa pagpaparehistro at ang iyong password. Kung nakalimutan o mali ang mga ito, maaaring hindi ka makapasok. Maaari ding hindi pa makapasok ang account na nananatiling Pending o Rejected, at maaaring hindi available ang account na naisuspindi. Kung patuloy ang problema, makipag-ugnayan sa iyong administrator.',
      },
      {
        question: 'Paano kung nakalimutan ko ang password ko?',
        answer: 'Wala pang nakapagtalang password reset o recovery feature ang HarborAI sa kasalukuyan, kaya hindi ka makakapag-reset nang mag-isa sa pamamagitan ng email o link. Sa ganitong sitwasyon, kailangan mong makipag-ugnayan sa isang awtorisadong administrator upang sila ay tulungan kang maibalik ang access sa iyong account.',
      },
      {
        question: 'Ano ang gagawin kapag may system error?',
        answer: 'Subukang i-refresh ang page at tiyaking may maayos na internet connection. Kung magpatuloy ang problema, itala ang mensaheng error at ang ginawa mong hakbang bago ito lumabas, pagkatapos ay makipag-ugnayan sa iyong administrator kapag available na ang official na contact channel. Huwag ibahagi sa publiko ang iyong account o password.',
      },
      {
        question: 'Paano ko makokontak ang HarborAI support?',
        answer: 'Wala pang nakapublish na opisyal na contact number o email address ng support ang HarborAI sa ngayon, kaya walang maaaring gamitin na direktang kanal. Ang pinakamaaaring kalakip sa isang awtorisadong administrator ng iyong organisasyon o lokal na tanggapan. Huwag gumamit ng mga contact detail na hindi opisyal na nanggaling sa HarborAI.',
      },
    ],
  },
];
