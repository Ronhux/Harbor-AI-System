import { DashboardText, useDashboardCopy } from '../../../i18n/DashboardCopy';
import { useEffect, useMemo, useRef, useState, type TransitionEvent } from 'react';
import { ChevronLeft, ChevronRight, MapPin, Package, Phone, Search, Sprout, X } from 'lucide-react';
import { Card, CardContent } from '../../../components/ui/card';
import { Button } from '../../../components/ui/button';
import { Badge } from '../../../components/ui/badge';
import { Input } from '../../../components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '../../../components/ui/select';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '../../../components/ui/dialog';
import { request } from '../../../../lib/api';
import { resolveListingImages } from '../../../../lib/image';

type Image = { id?: number; url: string; is_primary?: boolean };
type Product = {
  listing_id:number; product_name:string; category?:string; product_category?:string; description?:string;
  current_price_per_unit?:number; price_per_unit?:number; unit_of_measure?:string; unit?:string;
  quantity_available?:number; quantity?:number; location?:string; harvest_date?:string; expiry_date?:string;
  status:string; image_url?:string; image_path?:string; images?:Image[]; created_at?:string; updated_at?:string;
  producer:{id:number;name:string;contact_number?:string;location?:string;locality?:string;type?:string;primary_product_type?:string;verification_status:string};
};
type Props = {
  pendingOrder?: { producerId: number; listingId: number } | null;
  onPendingOrderHandled?: () => void;
  pendingProductListingId?: number | null;
  pendingProposalDraft?: { quantity: number; deliveryDate?: string; deliveryArrangement?: string } | null;
  onPendingProductHandled?: () => void;
};
const listingImages=(product:Product)=>resolveListingImages(product);

/* =========================================================
   FILTER AND SORT HELPERS
   Filtering is client side over the catalog already returned by
   /api/buyer/products, which only contains verified producers with an
   active or available listing that still has stock.
   ========================================================= */

const ALL_CATEGORIES='All products';
const ALL_LOCATIONS='All locations';
const ALL_PRODUCERS='All producers';
const DEFAULT_SORT='newest';

const SORT_OPTIONS: {value:string;label:string}[]=[
  {value:'newest',label:'Newest'},
  {value:'price_asc',label:'Price: Low to High'},
  {value:'price_desc',label:'Price: High to Low'},
  {value:'name_asc',label:'Name: A-Z'},
];

/** Trims a value and turns anything unusable into an empty string. */
const text=(value:unknown):string=>String(value??'').trim();

/** Case and whitespace insensitive key used for comparisons. */
const key=(value:unknown):string=>text(value).toLowerCase();

const categoryOf=(product:Product):string=>text(product.product_category||product.category)||'General';
const unitOf=(product:Product):string=>text(product.unit_of_measure||product.unit)||'unit';
const locationOf=(product:Product):string=>text(product.location||product.producer?.location);
const producerOf=(product:Product):string=>text(product.producer?.name);

/** Price as a finite number. Missing or unusable values fall back to 0. */
const priceOf=(product:Product):number=>{
  const price=Number(product.current_price_per_unit??product.price_per_unit);
  return Number.isFinite(price)?price:0;
};

/** Listing age as a timestamp. Unparseable dates fall back to 0 so sorting never fails. */
const createdAtOf=(product:Product):number=>{
  const parsed=Date.parse(text(product.created_at));
  return Number.isFinite(parsed)?parsed:0;
};

/**
 * Parses a price bound. Blank or non numeric input is treated as "no bound"
 * instead of becoming NaN and silently hiding every product.
 */
const parseBound=(value:string):number|null=>{
  const trimmed=value.trim();
  if(!trimmed) return null;
  const parsed=Number(trimmed);
  return Number.isFinite(parsed)&&parsed>=0?parsed:null;
};

/**
 * Builds an option list that keeps the first label seen for each
 * case insensitive key, so 'Rice' and 'rice' never become two chips.
 */
const uniqueOptions=(values:string[],allLabel:string):string[]=>{
  const seen=new Map<string,string>();
  values.forEach(value=>{const k=key(value); if(k&&!seen.has(k)) seen.set(k,value);});
  return [allLabel,...Array.from(seen.values()).sort((a,b)=>a.localeCompare(b))];
};

function ProductImage({src,alt,fit='cover',className=''}:{src:string;alt:string;fit?:'cover'|'contain';className?:string}){
  const copy = useDashboardCopy();
  const [failed,setFailed]=useState(!src);
  useEffect(()=>setFailed(!src),[src]);
  if(failed)return <div role="img" aria-label={copy("{0} image unavailable", [alt])} className={`flex h-full w-full items-center justify-center bg-[#0F9488]/10 text-[#0F9488] ${className}`}><Sprout className="h-12 w-12"/></div>;
  return <img src={src} alt={alt} loading="lazy" className={`h-full w-full ${fit==='contain'?'object-contain':'object-cover'} ${className}`} onError={()=>setFailed(true)}/>;
}

export default function BrowseProducts({pendingOrder,onPendingOrderHandled,pendingProductListingId,pendingProposalDraft,onPendingProductHandled}:Props){
  const copy = useDashboardCopy();
const [products,setProducts]=useState<Product[]>([]); const [loading,setLoading]=useState(true); const [error,setError]=useState('');
  const [search,setSearch]=useState(''); const [category,setCategory]=useState(ALL_CATEGORIES); const [selected,setSelected]=useState<Product|null>(null); const [imageIndex,setImageIndex]=useState(0); const [trackIndex,setTrackIndex]=useState(1); const [settling,setSettling]=useState(false);
  const [sort,setSort]=useState(DEFAULT_SORT); const [minPrice,setMinPrice]=useState(''); const [maxPrice,setMaxPrice]=useState('');
  const [location,setLocation]=useState(ALL_LOCATIONS); const [producer,setProducer]=useState(ALL_PRODUCERS);
  const [negotiating,setNegotiating]=useState(false); const [proposal,setProposal]=useState({price:'',quantity:'',charges:'0',delivery_date:'',delivery_arrangement:'',notes:''});
  const [proposalError,setProposalError]=useState(''); const [proposalSuccess,setProposalSuccess]=useState(''); const [proposalBusy,setProposalBusy]=useState(false);
  const [activeListings,setActiveListings]=useState<Set<number>>(new Set());
  const load=async()=>{setLoading(true);setError('');try{const [productsResult,negotiationsResult]=await Promise.all([request('/api/buyer/products'),request('/api/buyer/negotiations')]);setProducts(productsResult?.data||[]);setActiveListings(new Set((negotiationsResult?.data||[]).filter((n:any)=>!['Agreement Confirmed','Rejected','Cancelled','Expired'].includes(n.status)).map((n:any)=>Number(n.product?.listing_id)).filter(Boolean)))}catch(e:any){setError(e?.message||'Unable to load available products.')}finally{setLoading(false)}};
  useEffect(()=>{void load()},[]);
  useEffect(()=>{if(!pendingOrder||loading)return;const product=products.find(p=>p.listing_id===pendingOrder.listingId);if(product){setSelected(product);resetCarousel();setProposal({price:String(product.current_price_per_unit??product.price_per_unit??''),quantity:'',charges:'0',delivery_date:'',delivery_arrangement:'',notes:''});onPendingOrderHandled?.()}},[pendingOrder,loading,products]);
  useEffect(()=>{
    if(pendingProductListingId == null || loading) return;
    const product=products.find(p=>Number(p.listing_id)===Number(pendingProductListingId));
    if(product){
      setSelected(product);
      setNegotiating(Boolean(pendingProposalDraft));
      setProposalError('');
      setProposalSuccess('');
      setProposal({
        price: String(product.current_price_per_unit ?? product.price_per_unit ?? ''),
        quantity: pendingProposalDraft ? String(pendingProposalDraft.quantity) : '',
        charges: '0',
        delivery_date: pendingProposalDraft?.deliveryDate ?? '',
        delivery_arrangement: pendingProposalDraft?.deliveryArrangement ?? '',
        notes: '',
      });
      resetCarousel();
    }
    onPendingProductHandled?.();
  },[pendingProductListingId,pendingProposalDraft,loading,products]);
const categories=useMemo(()=>uniqueOptions(products.map(categoryOf),ALL_CATEGORIES),[products]);
  const locations=useMemo(()=>uniqueOptions(products.map(locationOf).filter(Boolean),ALL_LOCATIONS),[products]);
  const producers=useMemo(()=>uniqueOptions(products.map(producerOf).filter(Boolean),ALL_PRODUCERS),[products]);

  const minBound=parseBound(minPrice); const maxBound=parseBound(maxPrice);
  const reversedRange=minBound!==null&&maxBound!==null&&minBound>maxBound;

  const visible=useMemo(()=>{
    const needle=key(search);

    const filtered=products.filter(p=>{
      // Text search covers name, category, producer, both locations and the description.
      if(needle){
        const haystack=[p.product_name,p.product_category,p.category,p.producer?.name,p.location,p.producer?.location,p.description]
          .some(value=>key(value).includes(needle));
        if(!haystack) return false;
      }

      if(category!==ALL_CATEGORIES&&key(categoryOf(p))!==key(category)) return false;
      if(location!==ALL_LOCATIONS&&key(locationOf(p))!==key(location)) return false;
      if(producer!==ALL_PRODUCERS&&key(producerOf(p))!==key(producer)) return false;

      // Inclusive bounds. Compared against each listing's own price as listed;
      // units are never converted between one another.
      const price=priceOf(p);
      if(minBound!==null&&price<minBound) return false;
      if(maxBound!==null&&price>maxBound) return false;

      return true;
    });

    const sorted=[...filtered];
    if(sort==='price_asc') sorted.sort((a,b)=>priceOf(a)-priceOf(b));
    else if(sort==='price_desc') sorted.sort((a,b)=>priceOf(b)-priceOf(a));
    else if(sort==='name_asc') sorted.sort((a,b)=>key(a.product_name).localeCompare(key(b.product_name)));
    else sorted.sort((a,b)=>createdAtOf(b)-createdAtOf(a));

    return sorted;
  },[products,search,category,location,producer,sort,minBound,maxBound]);

  const hasActiveFilters=search.trim()!==''||category!==ALL_CATEGORIES||minBound!==null||maxBound!==null||location!==ALL_LOCATIONS||producer!==ALL_PRODUCERS||sort!==DEFAULT_SORT;

  const clearFilters=()=>{
    setSearch(''); setCategory(ALL_CATEGORIES); setMinPrice(''); setMaxPrice('');
    setLocation(ALL_LOCATIONS); setProducer(ALL_PRODUCERS); setSort(DEFAULT_SORT);
  };

  /**
   * Records one product view per listing per browser session.
   *
   * The sessionStorage marker is written before the request so repeated
   * renders, image carousel navigation and reopening the same dialog cannot
   * count twice, and so a development double effect is harmless too.
   * Tracking failures are swallowed: they must never stop the buyer from
   * opening the detail dialog or starting a negotiation.
   */
  const recordView=(listingId:number)=>{
    const marker=`harborai:viewed-listing:${listingId}`;

    try{
      if(sessionStorage.getItem(marker)) return;
      sessionStorage.setItem(marker,'1');
    }catch{
      // Storage unavailable (private mode or blocked). Skip tracking silently.
      return;
    }

    request(`/api/buyer/products/${listingId}/view`,{method:'POST'}).catch(()=>{
      // Allow a later visit to retry instead of remembering a failed attempt.
      try{ sessionStorage.removeItem(marker); }catch{ /* ignore */ }
    });
  };

  // A view is one dialog opening. Depend only on the listing id so closing,
  // reopening and re-rendering do not each trigger a new record.
  useEffect(()=>{
    const listingId=selected?.listing_id;
    if(!listingId) return;
    recordView(listingId);
  },[selected?.listing_id]);
  const images=selected?listingImages(selected):[]; const unit=selected?.unit_of_measure||selected?.unit||'unit';
  const imageCount=images.length;

  /**
   * Carousel layout.
   *
   * The strip holds every image flanked by a clone of the last image and a
   * clone of the first one, so wrapping is a single animated step in either
   * direction instead of sliding the whole strip backwards. Real images sit
   * at strip positions 1..imageCount.
   *
   * imageIndex is the real image and drives the thumbnails. trackIndex is
   * only the visual position and may sit on a clone for one frame while a
   * wrap settles. settleTo holds the pending correction in a ref so a
   * superseded step can never be applied after a rapid click.
   */
  const slides=imageCount>0?[images[imageCount-1],...images,images[0]]:[];
  const realIndexAt=(position:number)=>{
    if(imageCount===0) return 0;
    if(position===0) return imageCount-1;
    if(position===imageCount+1) return 0;
    return position-1;
  };

  const settleTo=useRef<number|null>(null);
  const settleTimer=useRef<number|null>(null);

  const clearSettleTimer=()=>{ if(settleTimer.current!==null){ window.clearTimeout(settleTimer.current); settleTimer.current=null; } };

  /** Drops any pending correction so a newer step always wins. */
  const cancelSettle=()=>{ settleTo.current=null; clearSettleTimer(); };

  /**
   * Applies a pending correction with the transition switched off, so the
   * strip silently snaps to the equivalent real slide instead of animating.
   */
  const applySettle=(target:number)=>{
    settleTo.current=null; clearSettleTimer();
    setSettling(true);
    setTrackIndex(target);
  };

  /**
   * Safety net for a transition that never reports completion, for example
   * when the tab is in the background. Only ever moves the strip onto the
   * identical picture, so firing late is harmless.
   */
  const armSettleFallback=(target:number)=>{
    clearSettleTimer();
    settleTimer.current=window.setTimeout(()=>{
      settleTimer.current=null;
      if(settleTo.current===target) applySettle(target);
    },400);
  };

  const showImage=(index:number)=>{
    if(imageCount===0) return;
    cancelSettle(); setSettling(false);
    setImageIndex(index);
    setTrackIndex(index+1);
  };

  const showNext=()=>{
    if(imageCount===0) return;
    cancelSettle(); setSettling(false);
    if(imageIndex>=imageCount-1){
      // Wrap onto the trailing clone, which already shows the first image.
      setImageIndex(0);
      settleTo.current=1;
      setTrackIndex(imageCount+1);
      armSettleFallback(1);
      return;
    }
    setImageIndex(imageIndex+1);
    setTrackIndex(imageIndex+2);
  };

  const showPrev=()=>{
    if(imageCount===0) return;
    cancelSettle(); setSettling(false);
    if(imageIndex<=0){
      // Wrap onto the leading clone, which already shows the last image.
      setImageIndex(imageCount-1);
      settleTo.current=imageCount;
      setTrackIndex(0);
      armSettleFallback(imageCount);
      return;
    }
    setImageIndex(imageIndex-1);
    setTrackIndex(imageIndex);
  };

  /**
   * Corrects the strip once a wrap transition finishes. Child elements also
   * emit transition events, so only this exact element and property count.
   */
  const handleTrackTransitionEnd=(event:TransitionEvent<HTMLDivElement>)=>{
    if(event.target!==event.currentTarget) return;
    if(event.propertyName!=='transform') return;
    const target=settleTo.current;
    if(target===null) return;
    applySettle(target);
  };

  /** Re-enables the transition once the settling frame has painted. */
  useEffect(()=>{ if(settling) setSettling(false); },[settling]);

  /** Puts the carousel back on the first image for a newly opened product. */
  const resetCarousel=()=>{
    cancelSettle(); setSettling(false);
    setImageIndex(0);
    setTrackIndex(1);
  };

  // Never leave a pending correction running after the page goes away.
  useEffect(()=>()=>{ clearSettleTimer(); },[]);
  const beginProposal=()=>{if(!selected)return;setProposal({price:String(selected.current_price_per_unit??selected.price_per_unit??''),quantity:'',charges:'0',delivery_date:'',delivery_arrangement:'',notes:''});setProposalError('');setProposalSuccess('');setNegotiating(true)};
  const submitProposal=async()=>{if(!selected)return;setProposalBusy(true);setProposalError('');try{const result=await request('/api/buyer/negotiations',{method:'POST',body:{listing_id:selected.listing_id,unit_price:Number(proposal.price),quantity:Number(proposal.quantity),additional_charges:Number(proposal.charges||0),delivery_date:proposal.delivery_date||null,delivery_arrangement:proposal.delivery_arrangement,notes:proposal.notes}});setProposalSuccess(`${result.message} Reference: ${result.data?.reference||''}`);setActiveListings(current=>new Set(current).add(selected.listing_id));setNegotiating(false)}catch(e:any){setProposalError(e?.message||'Could not submit the proposal.')}finally{setProposalBusy(false)}};

  return <div className="space-y-5 p-4 sm:p-6">
    <header><h1 className="text-2xl font-black text-foreground sm:text-3xl"><DashboardText text="Browse Products" /></h1><p className="mt-1 text-sm text-muted-foreground"><DashboardText text="View local agricultural products, then contact producers to discuss terms." /></p></header>
    <div className="space-y-3 rounded-2xl border border-border bg-card p-4">
       <div className="relative">
         <Search className="absolute left-3 top-3 h-4 w-4 text-muted-foreground"/>
         <Input className="pl-9" value={search} onChange={e=>setSearch(e.target.value)} placeholder={copy("Search products, categories, producers, locations, or descriptions")}/>
       </div>

       {/* categories: `categories` already carries the "All products" option first */}
       <div className="flex gap-2 overflow-x-auto pb-1">
         {categories.map(c=><button
           key={key(c)}
           className={`min-h-10 shrink-0 rounded-full px-4 text-sm font-bold ${key(category)===key(c)?'bg-[#123C5C] text-white':'border text-foreground'}`}
           onClick={()=>setCategory(c)}
         >{c}</button>)}
       </div>

       <div className="flex flex-wrap gap-2">
         <div className="w-full sm:w-48">
           <Select value={sort} onValueChange={setSort}>
             <SelectTrigger className="min-h-10" aria-label={copy("Sort products")}><SelectValue placeholder={copy("Sort")} /></SelectTrigger>
             <SelectContent>{SORT_OPTIONS.map(o=><SelectItem key={o.value} value={o.value}>{o.label}</SelectItem>)}</SelectContent>
           </Select>
         </div>

         <Input
           className="w-full sm:w-36"
           type="number"
           inputMode="decimal"
           min="0"
           step="0.01"
           value={minPrice}
           onChange={e=>setMinPrice(e.target.value)}
           placeholder={copy("Min price")}
           aria-label={copy("Minimum price")}
         />

         <Input
           className="w-full sm:w-36"
           type="number"
           inputMode="decimal"
           min="0"
           step="0.01"
           value={maxPrice}
           onChange={e=>setMaxPrice(e.target.value)}
           placeholder={copy("Max price")}
           aria-label={copy("Maximum price")}
         />

         <div className="w-full sm:w-48">
           <Select value={location} onValueChange={setLocation}>
             <SelectTrigger className="min-h-10" aria-label={copy("Filter by location")}><SelectValue placeholder={copy("All locations")} /></SelectTrigger>
             <SelectContent>{locations.map(l=><SelectItem key={key(l)} value={l}>{l}</SelectItem>)}</SelectContent>
           </Select>
         </div>

         <div className="w-full sm:w-48">
           <Select value={producer} onValueChange={setProducer}>
             <SelectTrigger className="min-h-10" aria-label={copy("Filter by producer")}><SelectValue placeholder={copy("All producers")} /></SelectTrigger>
             <SelectContent>{producers.map(p=><SelectItem key={key(p)} value={p}>{p}</SelectItem>)}</SelectContent>
           </Select>
         </div>
       </div>

       <p className="text-xs text-muted-foreground"><DashboardText text="Prices are compared per listed unit and are not converted between units." />{' '}{reversedRange&&<span className="ml-1 font-semibold text-[#92400E]"><DashboardText text="Minimum price is higher than maximum price." /></span>}
       </p>

       <div className="flex flex-wrap items-center justify-between gap-2">
         <p className="text-sm font-semibold text-foreground">
           {visible.length} {visible.length===1?copy("product"):copy("products")}{' '}<DashboardText text="found" />{' '}{!loading&&<span className="font-normal text-muted-foreground"><DashboardText text="of" />{' '}{products.length}</span>}
         </p>

         {hasActiveFilters&&(
           <Button variant="outline" className="min-h-10 border-[#0F9488] text-[#0F9488] hover:bg-[#0F9488]/10" onClick={clearFilters}>
             <X className="mr-1 h-4 w-4" aria-hidden="true"/><DashboardText text="Clear all filters" /></Button>
         )}
       </div>
     </div>
    {error&&<Card><CardContent className="p-6 text-center"><p role="alert" className="text-sm text-red-700">{error}</p><Button className="mt-3" onClick={()=>void load()}><DashboardText text="Retry" /></Button></CardContent></Card>}
    {loading?<p className="p-8 text-center text-muted-foreground"><DashboardText text="Loading available products…" /></p>:!error&&visible.length===0?<Card><CardContent className="p-8 text-center text-muted-foreground"><DashboardText text="No products match these filters." /></CardContent></Card>:!error&&<div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-3 xl:grid-cols-4">{visible.map(p=>{const productImages=listingImages(p);const price=Number(p.current_price_per_unit??p.price_per_unit??0);const u=p.unit_of_measure||p.unit||'unit';return <Card key={p.listing_id} className="group overflow-hidden rounded-2xl border-border transition hover:-translate-y-0.5 hover:shadow-lg"><button className="block w-full text-left" onClick={()=>{setSelected(p);resetCarousel()}} aria-label={copy("View {0} details", [p.product_name])}><div className="aspect-[4/3] overflow-hidden bg-[#0F9488]/10">{productImages[0]?<ProductImage src={productImages[0].url} alt={p.product_name} className="transition-transform group-hover:scale-[1.03]"/>:<div className="flex h-full items-center justify-center text-[#0F9488]"><Sprout className="h-12 w-12"/></div>}</div><div className="space-y-2 p-3 sm:p-4"><div className="flex items-start justify-between gap-2"><h2 className="line-clamp-2 min-h-10 font-bold text-foreground">{p.product_name}</h2><Badge className="bg-emerald-100 text-emerald-900"><DashboardText text="Available" /></Badge></div><p className="font-black text-[#0F766E]">₱{price.toLocaleString()} <span className="text-xs font-medium">/ {u}</span></p><p className="text-xs text-muted-foreground">{Number(p.quantity_available??p.quantity??0).toLocaleString()} {u}{' '}<DashboardText text="available" /></p><p className="truncate text-xs font-semibold text-foreground">{p.producer?.name}</p><p className="flex items-center gap-1 truncate text-xs text-muted-foreground"><MapPin size={13}/>{p.location||p.producer?.location||copy("Location not specified")}</p><p className="line-clamp-2 text-xs text-muted-foreground">{p.description}</p></div></button><div className="px-3 pb-3"><Button variant="outline" className="min-h-10 w-full border-[#0F9488] text-[#0F766E]" onClick={()=>{setSelected(p);setImageIndex(0)}}><DashboardText text="View Details" /></Button></div></Card>})}</div>}

    <Dialog open={!!selected} onOpenChange={open=>{if(!open){setSelected(null);setNegotiating(false)}}}><DialogContent className="flex max-h-[92dvh] w-[calc(100vw-1rem)] flex-col gap-0 overflow-hidden bg-card p-0 sm:max-w-4xl"><DialogHeader className="shrink-0 border-b border-border bg-card px-4 py-4 pr-14 sm:px-6 sm:pr-16"><DialogTitle className="break-words text-2xl font-black text-foreground">{selected?.product_name}</DialogTitle><DialogDescription><DashboardText text="Commodity details and negotiation contact" /></DialogDescription></DialogHeader>{selected&&<div className="grid min-h-0 min-w-0 gap-6 overflow-y-auto overscroll-contain p-4 sm:p-6"><section><div className="relative aspect-[4/3] overflow-hidden rounded-xl bg-[#0F9488]/10">{imageCount>0?<div className={`flex h-full w-full ${settling?'':'transition-transform duration-300 ease-out'}`} style={{transform:`translateX(-${trackIndex*100}%)`}} onTransitionEnd={handleTrackTransitionEnd}>{slides.map((im,position)=><div key={position} className="h-full w-full shrink-0"><ProductImage src={im.url} alt={`${selected.product_name} image ${realIndexAt(position)+1}`} fit="contain"/></div>)}</div>:<div className="flex h-full items-center justify-center text-[#0F9488]"><Package className="h-16 w-16"/></div>}{imageCount>1&&<><button aria-label={copy("Previous image")} className="absolute left-2 top-1/2 rounded-full bg-card p-2 shadow" onClick={showPrev}><ChevronLeft/></button><button aria-label={copy("Next image")} className="absolute right-2 top-1/2 rounded-full bg-card p-2 shadow" onClick={showNext}><ChevronRight/></button></>}</div>{imageCount>1&&<div className="mt-3 flex gap-2 overflow-x-auto">{images.map((im,i)=><button key={im.id??i} onClick={()=>showImage(i)} className={`h-16 w-20 shrink-0 overflow-hidden rounded-lg border-2 ${imageIndex===i?'border-[#0F9488]':'border-transparent'}`}><ProductImage src={im.url} alt={`${selected.product_name} image ${i+1}`} /></button>)}</div>}</section>
      <section className="min-w-0 space-y-4"><Badge>{selected.product_category||selected.category||copy("Product")}</Badge><p className="text-2xl font-black text-[#0F766E]">₱{Number(selected.current_price_per_unit??selected.price_per_unit??0).toLocaleString()} / {unit}</p><p className="text-sm text-muted-foreground">{Number(selected.quantity_available??selected.quantity??0).toLocaleString()} {unit}{' '}<DashboardText text="available ·" />{' '}{selected.status}</p>{selected.description&&<div><h3 className="font-bold text-foreground"><DashboardText text="Description" /></h3><p className="mt-1 whitespace-pre-wrap break-words text-sm leading-6 text-muted-foreground">{selected.description}</p></div>}<div className="rounded-xl border border-border p-4"><p className="font-bold text-foreground">{selected.producer.name}</p><p className="mt-1 flex items-center gap-1 text-sm text-muted-foreground"><MapPin size={14}/>{selected.producer.locality||selected.producer.location||selected.location||copy("Location not specified")}</p><p className="mt-1 text-xs text-muted-foreground">{selected.producer.verification_status} · {selected.producer.type||selected.producer.primary_product_type||copy("Producer")}</p>{selected.producer.contact_number&&<a href={`tel:${selected.producer.contact_number}`} className="mt-3 flex min-h-10 items-center gap-2 font-bold text-[#0F766E]"><Phone size={16}/><DashboardText text="Call" />{' '}{selected.producer.contact_number}</a>}</div>
      {proposalSuccess&&<p role="status" className="rounded-lg bg-emerald-50 p-3 text-sm text-emerald-900">{proposalSuccess}</p>}
      {!negotiating?<Button disabled={activeListings.has(selected.listing_id)} className="min-h-11 w-full bg-[#0F9488] font-bold text-white" onClick={beginProposal}>{activeListings.has(selected.listing_id)?copy("Proposal Submitted · Waiting for Producer Update"):copy("Record Negotiated Terms")}</Button>:<div className="space-y-3 rounded-xl bg-card p-4"><h3 className="font-bold text-foreground"><DashboardText text="Submit Negotiated Terms" /></h3><p className="text-xs text-muted-foreground"><DashboardText text="Call the producer first. This proposal is not an order or an agreement." /></p><p className="text-xs text-muted-foreground"><DashboardText text="Listed price: ₱" />{' '}{Number(selected.current_price_per_unit??selected.price_per_unit??0).toLocaleString()} / {unit}</p><label className="block text-sm"><DashboardText text="Proposed price per" />{' '}{unit}<Input type="number" min="0.01" step="0.01" value={proposal.price} onChange={e=>setProposal({...proposal,price:e.target.value})}/></label><label className="block text-sm"><DashboardText text="Quantity" /><Input type="number" min="0.001" step="0.001" max={selected.quantity_available??selected.quantity} value={proposal.quantity} onChange={e=>setProposal({...proposal,quantity:e.target.value})}/></label><p className="text-sm text-muted-foreground"><DashboardText text="Product subtotal: ₱" />{' '}{(Number(proposal.price||0)*Number(proposal.quantity||0)).toLocaleString()}</p><label className="block text-sm"><DashboardText text="Additional charges" /><Input type="number" min="0" step="0.01" value={proposal.charges} onChange={e=>setProposal({...proposal,charges:e.target.value})}/></label><p className="text-sm font-bold text-foreground"><DashboardText text="Proposed total: ₱" />{' '}{(Number(proposal.price||0)*Number(proposal.quantity||0)+Number(proposal.charges||0)).toLocaleString()}</p><label className="block text-sm"><DashboardText text="Proposed delivery date" /><Input type="date" value={proposal.delivery_date} onChange={e=>setProposal({...proposal,delivery_date:e.target.value})}/></label><label className="block text-sm"><DashboardText text="Delivery arrangement" /><Input value={proposal.delivery_arrangement} onChange={e=>setProposal({...proposal,delivery_arrangement:e.target.value})}/></label><label className="block text-sm"><DashboardText text="Notes" /><textarea className="mt-1 w-full rounded-md border p-2" value={proposal.notes} onChange={e=>setProposal({...proposal,notes:e.target.value})}/></label>{proposalError&&<p role="alert" className="text-sm text-red-700">{proposalError}</p>}<div className="flex flex-wrap gap-2"><Button disabled={proposalBusy||!Number(proposal.price)||!Number(proposal.quantity)||Number(proposal.quantity)>Number(selected.quantity_available??selected.quantity)} onClick={()=>void submitProposal()}>{proposalBusy?copy("Submitting…"):copy("Review and submit proposal")}</Button><Button variant="outline" onClick={()=>setNegotiating(false)}><DashboardText text="Back" /></Button></div></div>}</section></div>}</DialogContent></Dialog>
  </div>;
}
