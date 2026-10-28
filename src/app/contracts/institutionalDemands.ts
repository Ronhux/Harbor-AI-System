export type InstitutionalPostingType = 'market_inquiry' | 'supply_requirement' | 'official_procurement_reference';
export type InstitutionalDemandState = 'draft' | 'pending_review' | 'open' | 'under_review' | 'partially_fulfilled' | 'fulfilled' | 'cancelled' | 'expired';
export type SupplyOfferState = 'submitted' | 'under_review' | 'provisional' | 'rejected' | 'withdrawn' | 'cancelled' | 'expired';
export interface InstitutionalDemandItem {
  commodity: string; category: string; variety?: string | null; quantity: string;
  unit: 'kg' | 'g' | 'litre' | 'piece' | 'sack' | 'crate'; specifications: string; max_unit_price?: string | null;
}
export interface InstitutionalDemandInput {
  title: string; description: string; purpose: string; program?: 'sagip_saka' | 'kadiwa' | 'other' | null;
  posting_type: InstitutionalPostingType; procurement_reference?: string | null; items: InstitutionalDemandItem[];
  budget_kind: 'none' | 'indicative' | 'abc'; budget_amount?: string | null; currency: 'PHP';
  delivery_location: string; delivery_start: string; delivery_end: string; offer_deadline: string;
  partial_supply: boolean; required_documents: string[]; eligible_supplier_type: 'individual' | 'fca' | 'both';
  eligibility_requirements: string; rsbsa_required: boolean;
}
export interface InstitutionalSupplyInput {
  demand_version: number; supplier_type: 'individual' | 'fca'; representation_id?: number | null;
  items: { demand_item_id: number; quantity: string; unit_price?: string | null }[];
  availability_date: string; delivery_information: string; eligibility_declaration: string;
}
