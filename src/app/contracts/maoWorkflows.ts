/** Prepared contracts only; there are no live MAO workflow endpoints yet. */
export const MAO_DEMAND_PREVIEW_ENABLED = false;

export type DecimalQuantity = string;
export type DemandItemDraft = {
  commodity: string;
  quantity: DecimalQuantity;
  unit: string;
  specifications: string;
  max_price_per_unit?: string | null;
};

export type EnhancedDemandDraft = {
  posting_type: 'ordinary' | 'sagip_saka';
  purpose: string;
  items: DemandItemDraft[];
  offer_deadline: string;
  delivery_date: string;
  delivery_information: string;
  eligibility_requirements: string;
  partial_supply_allowed: boolean;
  budget_amount?: string | null;
  budget_currency: 'PHP';
  procurement_notice_reference?: string | null;
};

export type FcaApplicationDraft = {
  representative_position: string;
  authorization_statement: string;
  evidence_receipt: string;
};

export type KadiwaApplicationDraft = {
  commodities: Pick<DemandItemDraft, 'commodity' | 'quantity' | 'unit'>[];
  logistics_notes?: string;
};

export type KadiwaEventDraft = {
  name: string;
  venue: string;
  starts_at: string;
  ends_at: string;
  application_deadline: string;
  capacity: number;
  participation_requirements: string;
};

export type FcaReviewDraft = {
  decision: 'authorize' | 'reject' | 'revoke';
  review_reason: string;
  evidence_receipt?: string | null;
  expires_at?: string | null;
};

export type KadiwaReviewDraft = {
  decision: 'approve' | 'reject' | 'waitlist';
  review_reason: string;
};

/** Prepared persistence endpoints remain unregistered and disabled. */
export type FcaSubmissionRequest = FcaApplicationDraft & { submission_key: string };
export type FcaReviewRequest = Omit<FcaReviewDraft, 'evidence_receipt'> & {
  decision_key: string;
  expected_version: number;
};
export type FcaEvidenceReceipt = {
  receipt: string;
  status: 'clean' | 'quarantined';
  mime: 'application/pdf' | 'image/png' | 'image/jpeg';
  bytes: number;
};
export type FcaInspectionStatus = 'pending' | 'clean' | 'rejected' | 'error' | 'quarantined';
export type FcaInspectionSummary = {
  status: FcaInspectionStatus;
  checks: Record<string, FcaInspectionStatus>;
  usable: boolean;
};
export type FcaApplicationSummary = {
  id: number;
  fca_id: number;
  status: 'pending' | 'authorized' | 'rejected' | 'revoked';
  row_version: number;
  created_at: string;
  reviewed_at: string | null;
  expires_at: string | null;
  revoked_at: string | null;
};

export type PreparedWorkflow<T> = {
  submitted_details: T;
  proposed_attributes: Record<string, string | number | null>;
  required_reviews: string[];
  persisted: false;
  authorization_granted: false;
  activation_ready: false;
};
