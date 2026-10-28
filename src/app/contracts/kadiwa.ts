export type KadiwaEventStatus = 'draft' | 'open' | 'closed' | 'ongoing' | 'completed' | 'cancelled';
export type KadiwaApplicationStatus = 'pending' | 'approved' | 'rejected' | 'needs_information' | 'withdrawn' | 'cancelled';
export interface KadiwaEventPreview {
  id: string; title: string; venue: string; location: string; startsAt: string; endsAt: string;
  opensAt: string; closesAt: string; capacity: number | null; approvedCount: number;
  categories: readonly string[]; requirements: readonly string[]; status: KadiwaEventStatus;
}
export interface KadiwaApplicationInput {
  submission_key: string;
  commodities: { commodity: string; category: string; quantity: string; unit: string }[];
  participation_notes?: string;
}
export interface KadiwaReviewInput {
  decision: 'approve' | 'reject' | 'request_information' | 'withdraw';
  reason: string; version: number; operation_key: string;
}
