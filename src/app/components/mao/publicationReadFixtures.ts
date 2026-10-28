/** Generated from actual policy on unsaved synthetic models; no database or credentials. */
export default {
  "owner_draft": {
    "scope": "owner",
    "execution_enabled": false,
    "demand": {
      "demand": {
        "request_id": 102,
        "mao_state": "draft",
        "mao_version": 1,
        "created_at": "2030-01-02 09:00",
        "updated_at": "2030-01-03 10:00"
      },
      "posting": {
        "requesting_institution": "Synthetic institution",
        "title": "Synthetic multi-commodity requirement",
        "description": "Synthetic coordination only",
        "purpose": "Synthetic planning",
        "posting_type": "market_inquiry",
        "items": [
          {
            "commodity": "Synthetic cabbage",
            "category": "Vegetables",
            "quantity": "10.125",
            "unit": "kg",
            "specifications": "Synthetic grade",
            "variety": "Green",
            "max_unit_price": "40.10"
          },
          {
            "commodity": "Synthetic fish",
            "category": "Fishery",
            "quantity": "5.000",
            "unit": "kg",
            "specifications": "Synthetic freshness requirement"
          }
        ],
        "budget_kind": "none",
        "currency": "PHP",
        "delivery_location": "Synthetic hall",
        "delivery_start": "2030-01-11T09:00",
        "delivery_end": "2030-01-12T17:00",
        "offer_deadline": "2030-01-10T09:00",
        "partial_supply": true,
        "required_documents": [],
        "eligible_supplier_type": "both",
        "eligibility_requirements": "Server must recheck supplier eligibility",
        "rsbsa_required": false,
        "program": "other"
      },
      "items": [
        {
          "id": 301,
          "commodity": "Synthetic cabbage",
          "category": "Vegetables",
          "variety": "Green",
          "unit": "kg",
          "specifications": "Synthetic grade",
          "requested": "10.125",
          "provisional": "0.000",
          "remaining": "10.125",
          "delivered": "0.000"
        },
        {
          "id": 302,
          "commodity": "Synthetic fish",
          "category": "Fishery",
          "variety": null,
          "unit": "kg",
          "specifications": "Synthetic freshness requirement",
          "requested": "5.000",
          "provisional": "0.000",
          "remaining": "5.000",
          "delivered": "0.000"
        }
      ],
      "history": [
        {
          "action": "created",
          "previous_state": null,
          "new_state": "draft",
          "revision": 1,
          "created_at": "2030-01-02 09:00"
        }
      ]
    },
    "capabilities": {
      "view_own_demand": true,
      "view_private_draft": true,
      "edit_own_demand": true,
      "request_publication_review": true,
      "view_reviewer_data": false,
      "perform_reviewer_decision": false,
      "request_publication": false,
      "publish": false
    },
    "version": {
      "current": 1,
      "expected": 1,
      "matches": true
    },
    "review_read_authority": "not_established",
    "publication_authority": "not_established",
    "blockers": [
      "review_read_scope_not_established",
      "publication_deadline_and_owner_require_fresh_server_check"
    ]
  },
  "owner_pending": {
    "scope": "owner",
    "execution_enabled": false,
    "demand": {
      "demand": {
        "request_id": 102,
        "mao_state": "pending_review",
        "mao_version": 2,
        "created_at": "2030-01-02 09:00",
        "updated_at": "2030-01-03 10:00"
      },
      "posting": {
        "requesting_institution": "Synthetic institution",
        "title": "Synthetic multi-commodity requirement",
        "description": "Synthetic coordination only",
        "purpose": "Synthetic planning",
        "posting_type": "market_inquiry",
        "items": [
          {
            "commodity": "Synthetic cabbage",
            "category": "Vegetables",
            "quantity": "10.125",
            "unit": "kg",
            "specifications": "Synthetic grade",
            "variety": "Green",
            "max_unit_price": "40.10"
          },
          {
            "commodity": "Synthetic fish",
            "category": "Fishery",
            "quantity": "5.000",
            "unit": "kg",
            "specifications": "Synthetic freshness requirement"
          }
        ],
        "budget_kind": "none",
        "currency": "PHP",
        "delivery_location": "Synthetic hall",
        "delivery_start": "2030-01-11T09:00",
        "delivery_end": "2030-01-12T17:00",
        "offer_deadline": "2030-01-10T09:00",
        "partial_supply": true,
        "required_documents": [],
        "eligible_supplier_type": "both",
        "eligibility_requirements": "Server must recheck supplier eligibility",
        "rsbsa_required": false,
        "program": "other"
      },
      "items": [
        {
          "id": 301,
          "commodity": "Synthetic cabbage",
          "category": "Vegetables",
          "variety": "Green",
          "unit": "kg",
          "specifications": "Synthetic grade",
          "requested": "10.125",
          "provisional": "0.000",
          "remaining": "10.125",
          "delivered": "0.000"
        },
        {
          "id": 302,
          "commodity": "Synthetic fish",
          "category": "Fishery",
          "variety": null,
          "unit": "kg",
          "specifications": "Synthetic freshness requirement",
          "requested": "5.000",
          "provisional": "0.000",
          "remaining": "5.000",
          "delivered": "0.000"
        }
      ],
      "history": [
        {
          "action": "state_changed",
          "previous_state": "draft",
          "new_state": "pending_review",
          "revision": 2,
          "created_at": "2030-01-02 10:00"
        },
        {
          "action": "created",
          "previous_state": null,
          "new_state": "draft",
          "revision": 1,
          "created_at": "2030-01-02 09:00"
        }
      ]
    },
    "capabilities": {
      "view_own_demand": true,
      "view_private_draft": false,
      "edit_own_demand": true,
      "request_publication_review": false,
      "view_reviewer_data": false,
      "perform_reviewer_decision": false,
      "request_publication": false,
      "publish": false
    },
    "version": {
      "current": 2,
      "expected": 2,
      "matches": true
    },
    "review_read_authority": "not_established",
    "publication_authority": "not_established",
    "blockers": [
      "review_read_scope_not_established",
      "publication_deadline_and_owner_require_fresh_server_check"
    ]
  },
  "non_owner": {
    "scope": "restricted",
    "execution_enabled": false,
    "demand": null,
    "capabilities": {
      "view_own_demand": false,
      "view_private_draft": false,
      "edit_own_demand": false,
      "request_publication_review": false,
      "view_reviewer_data": false,
      "perform_reviewer_decision": false,
      "request_publication": false,
      "publish": false
    },
    "version": {
      "current": null,
      "expected": null,
      "matches": null
    },
    "review_read_authority": "not_established",
    "publication_authority": "not_established",
    "blockers": [
      "review_read_scope_not_established"
    ]
  },
  "unknown_reviewer": {
    "scope": "restricted",
    "execution_enabled": false,
    "demand": null,
    "capabilities": {
      "view_own_demand": false,
      "view_private_draft": false,
      "edit_own_demand": false,
      "request_publication_review": false,
      "view_reviewer_data": false,
      "perform_reviewer_decision": false,
      "request_publication": false,
      "publish": false
    },
    "version": {
      "current": null,
      "expected": null,
      "matches": null
    },
    "review_read_authority": "not_established",
    "publication_authority": "not_established",
    "blockers": [
      "review_read_scope_not_established"
    ]
  },
  "inactive": {
    "scope": "restricted",
    "execution_enabled": false,
    "demand": null,
    "capabilities": {
      "view_own_demand": false,
      "view_private_draft": false,
      "edit_own_demand": false,
      "request_publication_review": false,
      "view_reviewer_data": false,
      "perform_reviewer_decision": false,
      "request_publication": false,
      "publish": false
    },
    "version": {
      "current": null,
      "expected": null,
      "matches": null
    },
    "review_read_authority": "not_established",
    "publication_authority": "not_established",
    "blockers": [
      "review_read_scope_not_established"
    ]
  },
  "stale": {
    "scope": "owner",
    "execution_enabled": false,
    "demand": {
      "demand": {
        "request_id": 102,
        "mao_state": "draft",
        "mao_version": 1,
        "created_at": "2030-01-02 09:00",
        "updated_at": "2030-01-03 10:00"
      },
      "posting": {
        "requesting_institution": "Synthetic institution",
        "title": "Synthetic multi-commodity requirement",
        "description": "Synthetic coordination only",
        "purpose": "Synthetic planning",
        "posting_type": "market_inquiry",
        "items": [
          {
            "commodity": "Synthetic cabbage",
            "category": "Vegetables",
            "quantity": "10.125",
            "unit": "kg",
            "specifications": "Synthetic grade",
            "variety": "Green",
            "max_unit_price": "40.10"
          },
          {
            "commodity": "Synthetic fish",
            "category": "Fishery",
            "quantity": "5.000",
            "unit": "kg",
            "specifications": "Synthetic freshness requirement"
          }
        ],
        "budget_kind": "none",
        "currency": "PHP",
        "delivery_location": "Synthetic hall",
        "delivery_start": "2030-01-11T09:00",
        "delivery_end": "2030-01-12T17:00",
        "offer_deadline": "2030-01-10T09:00",
        "partial_supply": true,
        "required_documents": [],
        "eligible_supplier_type": "both",
        "eligibility_requirements": "Server must recheck supplier eligibility",
        "rsbsa_required": false,
        "program": "other"
      },
      "items": [
        {
          "id": 301,
          "commodity": "Synthetic cabbage",
          "category": "Vegetables",
          "variety": "Green",
          "unit": "kg",
          "specifications": "Synthetic grade",
          "requested": "10.125",
          "provisional": "0.000",
          "remaining": "10.125",
          "delivered": "0.000"
        },
        {
          "id": 302,
          "commodity": "Synthetic fish",
          "category": "Fishery",
          "variety": null,
          "unit": "kg",
          "specifications": "Synthetic freshness requirement",
          "requested": "5.000",
          "provisional": "0.000",
          "remaining": "5.000",
          "delivered": "0.000"
        }
      ],
      "history": [
        {
          "action": "created",
          "previous_state": null,
          "new_state": "draft",
          "revision": 1,
          "created_at": "2030-01-02 09:00"
        }
      ]
    },
    "capabilities": {
      "view_own_demand": true,
      "view_private_draft": true,
      "edit_own_demand": false,
      "request_publication_review": false,
      "view_reviewer_data": false,
      "perform_reviewer_decision": false,
      "request_publication": false,
      "publish": false
    },
    "version": {
      "current": 1,
      "expected": 2,
      "matches": false
    },
    "review_read_authority": "not_established",
    "publication_authority": "not_established",
    "blockers": [
      "review_read_scope_not_established",
      "version_conflict",
      "publication_deadline_and_owner_require_fresh_server_check"
    ]
  },
  "missing_version": {
    "scope": "owner",
    "execution_enabled": false,
    "demand": {
      "demand": {
        "request_id": 102,
        "mao_state": "draft",
        "mao_version": 1,
        "created_at": "2030-01-02 09:00",
        "updated_at": "2030-01-03 10:00"
      },
      "posting": {
        "requesting_institution": "Synthetic institution",
        "title": "Synthetic multi-commodity requirement",
        "description": "Synthetic coordination only",
        "purpose": "Synthetic planning",
        "posting_type": "market_inquiry",
        "items": [
          {
            "commodity": "Synthetic cabbage",
            "category": "Vegetables",
            "quantity": "10.125",
            "unit": "kg",
            "specifications": "Synthetic grade",
            "variety": "Green",
            "max_unit_price": "40.10"
          },
          {
            "commodity": "Synthetic fish",
            "category": "Fishery",
            "quantity": "5.000",
            "unit": "kg",
            "specifications": "Synthetic freshness requirement"
          }
        ],
        "budget_kind": "none",
        "currency": "PHP",
        "delivery_location": "Synthetic hall",
        "delivery_start": "2030-01-11T09:00",
        "delivery_end": "2030-01-12T17:00",
        "offer_deadline": "2030-01-10T09:00",
        "partial_supply": true,
        "required_documents": [],
        "eligible_supplier_type": "both",
        "eligibility_requirements": "Server must recheck supplier eligibility",
        "rsbsa_required": false,
        "program": "other"
      },
      "items": [
        {
          "id": 301,
          "commodity": "Synthetic cabbage",
          "category": "Vegetables",
          "variety": "Green",
          "unit": "kg",
          "specifications": "Synthetic grade",
          "requested": "10.125",
          "provisional": "0.000",
          "remaining": "10.125",
          "delivered": "0.000"
        },
        {
          "id": 302,
          "commodity": "Synthetic fish",
          "category": "Fishery",
          "variety": null,
          "unit": "kg",
          "specifications": "Synthetic freshness requirement",
          "requested": "5.000",
          "provisional": "0.000",
          "remaining": "5.000",
          "delivered": "0.000"
        }
      ],
      "history": [
        {
          "action": "created",
          "previous_state": null,
          "new_state": "draft",
          "revision": 1,
          "created_at": "2030-01-02 09:00"
        }
      ]
    },
    "capabilities": {
      "view_own_demand": true,
      "view_private_draft": true,
      "edit_own_demand": false,
      "request_publication_review": false,
      "view_reviewer_data": false,
      "perform_reviewer_decision": false,
      "request_publication": false,
      "publish": false
    },
    "version": {
      "current": 1,
      "expected": null,
      "matches": null
    },
    "review_read_authority": "not_established",
    "publication_authority": "not_established",
    "blockers": [
      "review_read_scope_not_established",
      "expected_version_required",
      "publication_deadline_and_owner_require_fresh_server_check"
    ]
  },
  "offers_unknown": {
    "scope": "owner",
    "execution_enabled": false,
    "demand": {
      "demand": {
        "request_id": 102,
        "mao_state": "draft",
        "mao_version": 1,
        "created_at": "2030-01-02 09:00",
        "updated_at": "2030-01-03 10:00"
      },
      "posting": {
        "requesting_institution": "Synthetic institution",
        "title": "Synthetic multi-commodity requirement",
        "description": "Synthetic coordination only",
        "purpose": "Synthetic planning",
        "posting_type": "market_inquiry",
        "items": [
          {
            "commodity": "Synthetic cabbage",
            "category": "Vegetables",
            "quantity": "10.125",
            "unit": "kg",
            "specifications": "Synthetic grade",
            "variety": "Green",
            "max_unit_price": "40.10"
          },
          {
            "commodity": "Synthetic fish",
            "category": "Fishery",
            "quantity": "5.000",
            "unit": "kg",
            "specifications": "Synthetic freshness requirement"
          }
        ],
        "budget_kind": "none",
        "currency": "PHP",
        "delivery_location": "Synthetic hall",
        "delivery_start": "2030-01-11T09:00",
        "delivery_end": "2030-01-12T17:00",
        "offer_deadline": "2030-01-10T09:00",
        "partial_supply": true,
        "required_documents": [],
        "eligible_supplier_type": "both",
        "eligibility_requirements": "Server must recheck supplier eligibility",
        "rsbsa_required": false,
        "program": "other"
      },
      "items": [
        {
          "id": 301,
          "commodity": "Synthetic cabbage",
          "category": "Vegetables",
          "variety": "Green",
          "unit": "kg",
          "specifications": "Synthetic grade",
          "requested": "10.125",
          "provisional": "0.000",
          "remaining": "10.125",
          "delivered": "0.000"
        },
        {
          "id": 302,
          "commodity": "Synthetic fish",
          "category": "Fishery",
          "variety": null,
          "unit": "kg",
          "specifications": "Synthetic freshness requirement",
          "requested": "5.000",
          "provisional": "0.000",
          "remaining": "5.000",
          "delivered": "0.000"
        }
      ],
      "history": [
        {
          "action": "created",
          "previous_state": null,
          "new_state": "draft",
          "revision": 1,
          "created_at": "2030-01-02 09:00"
        }
      ]
    },
    "capabilities": {
      "view_own_demand": true,
      "view_private_draft": true,
      "edit_own_demand": false,
      "request_publication_review": true,
      "view_reviewer_data": false,
      "perform_reviewer_decision": false,
      "request_publication": false,
      "publish": false
    },
    "version": {
      "current": 1,
      "expected": 1,
      "matches": true
    },
    "review_read_authority": "not_established",
    "publication_authority": "not_established",
    "blockers": [
      "review_read_scope_not_established",
      "retained_offer_state_unverified",
      "publication_deadline_and_owner_require_fresh_server_check"
    ]
  },
  "official": {
    "scope": "owner",
    "execution_enabled": false,
    "demand": {
      "demand": {
        "request_id": 102,
        "mao_state": "draft",
        "mao_version": 1,
        "created_at": "2030-01-02 09:00",
        "updated_at": "2030-01-03 10:00"
      },
      "posting": {
        "requesting_institution": "Synthetic institution",
        "title": "Synthetic multi-commodity requirement",
        "description": "Synthetic coordination only",
        "purpose": "Synthetic planning",
        "posting_type": "official_procurement_reference",
        "items": [
          {
            "commodity": "Synthetic cabbage",
            "category": "Vegetables",
            "quantity": "10.125",
            "unit": "kg",
            "specifications": "Synthetic grade",
            "variety": "Green",
            "max_unit_price": "40.10"
          },
          {
            "commodity": "Synthetic fish",
            "category": "Fishery",
            "quantity": "5.000",
            "unit": "kg",
            "specifications": "Synthetic freshness requirement"
          }
        ],
        "budget_kind": "none",
        "currency": "PHP",
        "delivery_location": "Synthetic hall",
        "delivery_start": "2030-01-11T09:00",
        "delivery_end": "2030-01-12T17:00",
        "offer_deadline": "2030-01-10T09:00",
        "partial_supply": true,
        "required_documents": [],
        "eligible_supplier_type": "both",
        "eligibility_requirements": "Server must recheck supplier eligibility",
        "rsbsa_required": false,
        "program": "other",
        "procurement_reference": "Synthetic external reference"
      },
      "items": [
        {
          "id": 301,
          "commodity": "Synthetic cabbage",
          "category": "Vegetables",
          "variety": "Green",
          "unit": "kg",
          "specifications": "Synthetic grade",
          "requested": "10.125",
          "provisional": "0.000",
          "remaining": "10.125",
          "delivered": "0.000"
        },
        {
          "id": 302,
          "commodity": "Synthetic fish",
          "category": "Fishery",
          "variety": null,
          "unit": "kg",
          "specifications": "Synthetic freshness requirement",
          "requested": "5.000",
          "provisional": "0.000",
          "remaining": "5.000",
          "delivered": "0.000"
        }
      ],
      "history": [
        {
          "action": "created",
          "previous_state": null,
          "new_state": "draft",
          "revision": 1,
          "created_at": "2030-01-02 09:00"
        }
      ]
    },
    "capabilities": {
      "view_own_demand": true,
      "view_private_draft": true,
      "edit_own_demand": true,
      "request_publication_review": true,
      "view_reviewer_data": false,
      "perform_reviewer_decision": false,
      "request_publication": false,
      "publish": false
    },
    "version": {
      "current": 1,
      "expected": 1,
      "matches": true
    },
    "review_read_authority": "not_established",
    "publication_authority": "not_established",
    "blockers": [
      "review_read_scope_not_established",
      "official_publication_rules_unapproved",
      "publication_deadline_and_owner_require_fresh_server_check"
    ]
  },
  "documents": {
    "scope": "owner",
    "execution_enabled": false,
    "demand": {
      "demand": {
        "request_id": 102,
        "mao_state": "draft",
        "mao_version": 1,
        "created_at": "2030-01-02 09:00",
        "updated_at": "2030-01-03 10:00"
      },
      "posting": {
        "requesting_institution": "Synthetic institution",
        "title": "Synthetic multi-commodity requirement",
        "description": "Synthetic coordination only",
        "purpose": "Synthetic planning",
        "posting_type": "market_inquiry",
        "items": [
          {
            "commodity": "Synthetic cabbage",
            "category": "Vegetables",
            "quantity": "10.125",
            "unit": "kg",
            "specifications": "Synthetic grade",
            "variety": "Green",
            "max_unit_price": "40.10"
          },
          {
            "commodity": "Synthetic fish",
            "category": "Fishery",
            "quantity": "5.000",
            "unit": "kg",
            "specifications": "Synthetic freshness requirement"
          }
        ],
        "budget_kind": "none",
        "currency": "PHP",
        "delivery_location": "Synthetic hall",
        "delivery_start": "2030-01-11T09:00",
        "delivery_end": "2030-01-12T17:00",
        "offer_deadline": "2030-01-10T09:00",
        "partial_supply": true,
        "required_documents": [
          "Synthetic document description"
        ],
        "eligible_supplier_type": "both",
        "eligibility_requirements": "Server must recheck supplier eligibility",
        "rsbsa_required": false,
        "program": "other"
      },
      "items": [
        {
          "id": 301,
          "commodity": "Synthetic cabbage",
          "category": "Vegetables",
          "variety": "Green",
          "unit": "kg",
          "specifications": "Synthetic grade",
          "requested": "10.125",
          "provisional": "0.000",
          "remaining": "10.125",
          "delivered": "0.000"
        },
        {
          "id": 302,
          "commodity": "Synthetic fish",
          "category": "Fishery",
          "variety": null,
          "unit": "kg",
          "specifications": "Synthetic freshness requirement",
          "requested": "5.000",
          "provisional": "0.000",
          "remaining": "5.000",
          "delivered": "0.000"
        }
      ],
      "history": [
        {
          "action": "created",
          "previous_state": null,
          "new_state": "draft",
          "revision": 1,
          "created_at": "2030-01-02 09:00"
        }
      ]
    },
    "capabilities": {
      "view_own_demand": true,
      "view_private_draft": true,
      "edit_own_demand": true,
      "request_publication_review": true,
      "view_reviewer_data": false,
      "perform_reviewer_decision": false,
      "request_publication": false,
      "publish": false
    },
    "version": {
      "current": 1,
      "expected": 1,
      "matches": true
    },
    "review_read_authority": "not_established",
    "publication_authority": "not_established",
    "blockers": [
      "review_read_scope_not_established",
      "private_document_validation_unavailable",
      "publication_deadline_and_owner_require_fresh_server_check"
    ]
  },
  "malformed": {
    "scope": "owner",
    "execution_enabled": false,
    "demand": {
      "demand": {
        "request_id": 102,
        "mao_state": "draft",
        "mao_version": 1,
        "created_at": "2030-01-02 09:00",
        "updated_at": "2030-01-03 10:00"
      },
      "posting": {
        "requesting_institution": "Synthetic institution",
        "title": "Synthetic multi-commodity requirement",
        "description": "Synthetic coordination only",
        "purpose": "Synthetic planning",
        "posting_type": "market_inquiry",
        "items": [
          {
            "commodity": "Synthetic cabbage",
            "category": "Vegetables",
            "quantity": "10.125",
            "unit": "kg",
            "specifications": "Synthetic grade",
            "variety": "Green",
            "max_unit_price": "40.10"
          },
          {
            "commodity": "Synthetic fish",
            "category": "Fishery",
            "quantity": "5.000",
            "unit": "kg",
            "specifications": "Synthetic freshness requirement"
          }
        ],
        "budget_kind": "none",
        "currency": "PHP",
        "delivery_location": "Synthetic hall",
        "delivery_start": "2030-01-11T09:00",
        "delivery_end": "2030-01-12T17:00",
        "offer_deadline": "2030-01-10T09:00",
        "partial_supply": true,
        "required_documents": [],
        "eligible_supplier_type": "both",
        "eligibility_requirements": "Server must recheck supplier eligibility",
        "rsbsa_required": false,
        "program": "other"
      },
      "items": [
        {
          "id": 301,
          "commodity": "Synthetic cabbage",
          "category": "Vegetables",
          "variety": "Green",
          "unit": "kg",
          "specifications": "Synthetic grade",
          "requested": "10.125",
          "provisional": "0.000",
          "remaining": "10.125",
          "delivered": "0.000"
        },
        {
          "id": 302,
          "commodity": "Synthetic fish",
          "category": "Fishery",
          "variety": null,
          "unit": "kg",
          "specifications": "Synthetic freshness requirement",
          "requested": "5.000",
          "provisional": "0.000",
          "remaining": "5.000",
          "delivered": "0.000"
        }
      ],
      "history": [
        {
          "action": "created",
          "previous_state": null,
          "new_state": "draft",
          "revision": 1,
          "created_at": "2030-01-02 09:00"
        }
      ]
    },
    "capabilities": {
      "publish": true
    },
    "version": {
      "current": 1,
      "expected": 1,
      "matches": true
    },
    "review_read_authority": "not_established",
    "publication_authority": "not_established",
    "blockers": [
      "review_read_scope_not_established",
      "publication_deadline_and_owner_require_fresh_server_check"
    ]
  }
};
