# HarborAI Product Backlog

**Project**: HarborAI - AI-Enabled Platform for Farmers & Fishers in Aparri, Cagayan  
**Last Updated**: February 15, 2026  
**Prioritization Method**: MoSCoW (Must Have, Should Have, Could Have, Won't Have)

---

## **PHASE 1: MVP - MUST HAVE (P0 - Critical)**

### **1.1 Core Authentication & Authorization**
**Priority**: P0 | **Effort**: 8 points | **Sprint**: 1

**User Stories**:
- As a user, I want to register with role selection so I can access role-specific features
- As a user, I want to login securely so my data is protected
- As an admin, I want to approve/reject producer registrations to ensure data quality

**Acceptance Criteria**:
- ✅ Email/password authentication
- ✅ Role-based access control (Admin, Producer, Buyer)
- ✅ Session management and logout
- ⬜ Email verification workflow
- ⬜ Password reset functionality
- ⬜ Producer verification approval workflow

---

### **1.2 Producer Profile & Product Listings**
**Priority**: P0 | **Effort**: 13 points | **Sprint**: 2

**User Stories**:
- As a producer, I want to create my farm/fishing profile so buyers can find me
- As a producer, I want to list my products with photos, prices, and quantities
- As a buyer, I want to browse verified producers to find suppliers

**Acceptance Criteria**:
- ✅ Producer registration form with farm details
- ✅ Digital stall/product catalog creation
- ✅ Product CRUD operations (Create, Read, Update, Delete)
- ⬜ Image upload for products (currently using placeholders)
- ⬜ Inventory quantity management
- ⬜ Product availability calendar
- ⬜ Producer verification badge system

---

### **1.3 Institutional Buyer Demand Posting**
**Priority**: P0 | **Effort**: 8 points | **Sprint**: 2

**User Stories**:
- As a buyer, I want to post my procurement needs so producers can respond
- As a producer, I want to see relevant demand requests to bid on opportunities
- As an admin, I want to monitor all procurement activities

**Acceptance Criteria**:
- ✅ Demand posting form with specifications
- ⬜ Producer-buyer matching algorithm
- ⬜ Notification system for matched opportunities
- ⬜ Bid/quote submission system
- ⬜ Demand expiry and status tracking

---

### **1.4 Basic Order Management**
**Priority**: P0 | **Effort**: 13 points | **Sprint**: 3

**User Stories**:
- As a buyer, I want to place orders with selected producers
- As a producer, I want to accept/reject orders and track fulfillment
- As both parties, I want to see order history and status

**Acceptance Criteria**:
- ✅ Order creation workflow
- ⬜ Order status tracking (Pending, Confirmed, In Transit, Delivered, Cancelled)
- ⬜ Producer order acceptance/rejection
- ⬜ Delivery scheduling
- ⬜ Order confirmation notifications
- ⬜ Basic order analytics in dashboards

---

### **1.5 Admin Monitoring Dashboard**
**Priority**: P0 | **Effort**: 8 points | **Sprint**: 3

**User Stories**:
- As an admin, I want to see platform metrics to monitor system health
- As an admin, I want to view all producers and buyers for oversight
- As an admin, I want to generate reports for government stakeholders

**Acceptance Criteria**:
- ✅ Dashboard with key metrics (users, orders, transactions)
- ✅ Producer registry with search/filter
- ✅ Basic charts and visualizations
- ⬜ Real-time data updates
- ⬜ Exportable reports (PDF, Excel)
- ⬜ Alert system for critical issues

---

## **PHASE 2: ENHANCED FEATURES - SHOULD HAVE (P1 - High)**

### **2.1 Government Program Matching (NLP-Based)**
**Priority**: P1 | **Effort**: 21 points | **Sprint**: 4-5

**User Stories**:
- As a producer, I want to see which government programs I'm eligible for
- As a producer, I want AI to analyze my profile and recommend programs
- As an admin, I want to track program enrollment and success rates

**Acceptance Criteria**:
- ✅ Program information pages (5 programs)
- ⬜ NLP algorithm to match producer profiles with program criteria
- ⬜ Eligibility scoring system
- ⬜ Application submission tracking
- ⬜ Document upload for program applications
- ⬜ Admin approval workflow for program enrollment
- ⬜ Integration with DA/BFAR/LGU systems (API)

**Programs to Support**:
1. Sagip Saka Act (RA 11321)
2. FFEDIS (Fuel/Ice/Engine Subsidy)
3. Kadiwa ni Ani at Kita Program
4. BFAR Programs (Bangus, Tilapia, Aquaculture)
5. DA-RFO II Regional Programs
6. LGU Direct Procurement Initiatives

---

### **2.2 AI-Driven Enterprise Recommendations**
**Priority**: P1 | **Effort**: 21 points | **Sprint**: 5-6

**User Stories**:
- As a producer, I want personalized crop/fish recommendations based on my location
- As a producer, I want to see market trends to decide what to produce
- As a producer, I want planting/harvesting schedule suggestions

**Acceptance Criteria**:
- ✅ Recommendation dashboard UI
- ⬜ Machine learning model for crop/fish recommendations
- ⬜ Integration with weather data (PAGASA API)
- ⬜ Soil/water quality analysis integration
- ⬜ Market demand forecasting for Aparri region
- ⬜ Seasonal calendar optimization
- ⬜ ROI projections for recommended enterprises

**Data Sources Needed**:
- Historical yield data
- Market price history
- Weather patterns
- Soil/water quality maps
- Buyer demand patterns

---

### **2.3 Price Insights & Market Intelligence**
**Priority**: P1 | **Effort**: 13 points | **Sprint**: 6

**User Stories**:
- As a producer, I want to see current market prices to price competitively
- As a buyer, I want historical price trends to budget procurement
- As both, I want price forecasts to plan ahead

**Acceptance Criteria**:
- ✅ Price trend charts (historical)
- ⬜ Integration with DA Price Monitoring API
- ⬜ Real-time price updates from platform transactions
- ⬜ Price comparison by region
- ⬜ AI-based price forecasting (7-30 day outlook)
- ⬜ Price alerts for significant changes
- ⬜ Seasonal price pattern analysis

---

### **2.4 Supply-Demand Analytics**
**Priority**: P1 | **Effort**: 13 points | **Sprint**: 7

**User Stories**:
- As an admin, I want to see supply-demand gaps to inform policy
- As an admin, I want predictive analytics for future procurement needs
- As a buyer, I want to see supply availability forecasts

**Acceptance Criteria**:
- ✅ Supply-demand charts and visualizations
- ⬜ Predictive analytics using historical data
- ⬜ Gap analysis by product category
- ⬜ Regional supply heatmaps
- ⬜ Demand forecasting for institutional buyers
- ⬜ Automated reports for DA/BFAR/LGUs

---

### **2.5 Mobile-Responsive Design**
**Priority**: P1 | **Effort**: 13 points | **Sprint**: 7-8

**User Stories**:
- As a producer in the field, I want to access the platform on my smartphone
- As a user, I want the interface to adapt to my device screen size

**Acceptance Criteria**:
- ✅ Responsive layouts for all pages
- ⬜ Mobile-optimized navigation
- ⬜ Touch-friendly interactions
- ⬜ Offline capability for key features
- ⬜ Progressive Web App (PWA) implementation
- ⬜ Mobile camera integration for product photos

---

## **PHASE 3: ADVANCED FEATURES - COULD HAVE (P2 - Medium)**

### **3.1 In-App Messaging & Negotiations**
**Priority**: P2 | **Effort**: 13 points | **Sprint**: 9

**User Stories**:
- As a buyer, I want to message producers directly to negotiate
- As a producer, I want to receive inquiries and respond promptly
- As both, I want message history for reference

**Acceptance Criteria**:
- ⬜ Real-time chat interface
- ⬜ Message notifications (email, SMS, push)
- ⬜ Attachment support for documents/images
- ⬜ Read receipts and typing indicators
- ⬜ Conversation archiving
- ⬜ Automated message translation (Ilocano, Tagalog, English)

---

### **3.2 Payment Integration**
**Priority**: P2 | **Effort**: 21 points | **Sprint**: 10-11

**User Stories**:
- As a producer, I want to receive payments digitally
- As a buyer, I want to pay securely through the platform
- As both, I want payment receipts and transaction history

**Acceptance Criteria**:
- ⬜ Integration with GCash/PayMaya
- ⬜ Bank transfer support (InstaPay/PESONet)
- ⬜ Escrow system for order security
- ⬜ Payment status tracking
- ⬜ Automated invoicing
- ⬜ Financial reports for tax compliance
- ⬜ Commission/transaction fee system

---

### **3.3 Logistics & Delivery Tracking**
**Priority**: P2 | **Effort**: 13 points | **Sprint**: 11-12

**User Stories**:
- As a buyer, I want to track delivery status in real-time
- As a producer, I want to update delivery milestones
- As both, I want proof of delivery confirmation

**Acceptance Criteria**:
- ⬜ GPS-based delivery tracking
- ⬜ Integration with logistics providers
- ⬜ Delivery milestone updates
- ⬜ Photo proof of delivery
- ⬜ Digital signature capture
- ⬜ Delivery performance metrics

---

### **3.4 Ratings & Reviews System**
**Priority**: P2 | **Effort**: 8 points | **Sprint**: 12

**User Stories**:
- As a buyer, I want to rate producers based on quality and service
- As a producer, I want to build reputation through good ratings
- As both, I want to see reviews before transacting

**Acceptance Criteria**:
- ⬜ Star rating system (1-5 stars)
- ⬜ Written review submission
- ⬜ Review moderation by admin
- ⬜ Rating badges (Top Rated, Verified Seller)
- ⬜ Response to reviews capability
- ⬜ Rating analytics in dashboards

---

### **3.5 Cooperative/Group Management**
**Priority**: P2 | **Effort**: 13 points | **Sprint**: 13

**User Stories**:
- As a cooperative leader, I want to manage multiple producer members
- As a member, I want my sales to contribute to cooperative metrics
- As a buyer, I want to order from cooperatives in bulk

**Acceptance Criteria**:
- ⬜ Cooperative profile creation
- ⬜ Member invitation and management
- ⬜ Aggregated product listings
- ⬜ Revenue distribution settings
- ⬜ Cooperative-level analytics
- ⬜ Bulk ordering workflows

---

### **3.6 Quality Certification & Traceability**
**Priority**: P2 | **Effort**: 13 points | **Sprint**: 14

**User Stories**:
- As a producer, I want to showcase my certifications (Organic, GAP, HACCP)
- As a buyer, I want to verify product origin and quality
- As a consumer, I want farm-to-table traceability

**Acceptance Criteria**:
- ⬜ Certificate upload and verification
- ⬜ QR code generation for products
- ⬜ Blockchain-based traceability (optional)
- ⬜ Batch tracking system
- ⬜ Quality inspection records
- ⬜ Integration with Bureau of Agriculture and Fisheries Standards

---

### **3.7 Multi-Language Support**
**Priority**: P2 | **Effort**: 8 points | **Sprint**: 14

**User Stories**:
- As an Ilocano speaker, I want the interface in my native language
- As a user, I want to switch between languages easily

**Acceptance Criteria**:
- ⬜ English, Tagalog, Ilocano language options
- ⬜ Language switcher in navigation
- ⬜ Translated content for all static pages
- ⬜ RTL support if needed
- ⬜ Locale-specific date/number formatting

---

## **PHASE 4: FUTURE ENHANCEMENTS - WON'T HAVE (NOW) (P3 - Low)**

### **4.1 IoT Integration**
**Priority**: P3 | **Effort**: 21 points

**Description**: Integration with IoT sensors for soil moisture, water quality, temperature monitoring to enhance AI recommendations.

---

### **4.2 Drone & Satellite Imagery**
**Priority**: P3 | **Effort**: 34 points

**Description**: Use satellite imagery and drone data for crop health monitoring, yield estimation, and disaster assessment.

---

### **4.3 Insurance Integration**
**Priority**: P3 | **Effort**: 21 points

**Description**: Integration with PCIC (Philippine Crop Insurance Corporation) for crop/livestock/fisheries insurance enrollment and claims.

---

### **4.4 Credit/Financing Marketplace**
**Priority**: P3 | **Effort**: 34 points

**Description**: Connect producers with microfinance institutions, banks, and lending platforms for agricultural loans.

---

### **4.5 Marketplace Expansion**
**Priority**: P3 | **Effort**: 34 points

**Description**: Expand beyond institutional buyers to include retailers, restaurants, and direct-to-consumer sales.

---

### **4.6 Climate Adaptation Tools**
**Priority**: P3 | **Effort**: 21 points

**Description**: Climate change impact modeling, disaster preparedness recommendations, and resilience scoring.

---

### **4.7 Social Commerce Features**
**Priority**: P3 | **Effort**: 13 points

**Description**: Social media integration, producer stories/blogs, community forums, and knowledge sharing.

---

### **4.8 AI Chatbot Support**
**Priority**: P3 | **Effort**: 13 points

**Description**: 24/7 AI-powered customer support chatbot for common questions and troubleshooting.

---

## **TECHNICAL DEBT & INFRASTRUCTURE**

### **T1. Backend API Development**
**Priority**: P0 | **Effort**: 34 points | **Sprint**: 1-3

**Tasks**:
- ⬜ Set up Supabase backend
- ⬜ Database schema design and migration
- ⬜ RESTful API endpoints
- ⬜ Authentication API (JWT, OAuth)
- ⬜ File storage for images/documents
- ⬜ API rate limiting and security

---

### **T2. Database Design**
**Priority**: P0 | **Effort**: 13 points | **Sprint**: 1

**Tables Needed**:
- ⬜ users (with role management)
- ⬜ producers (farm/fishing profiles)
- ⬜ institutional_buyers
- ⬜ products
- ⬜ orders
- ⬜ demand_requests
- ⬜ government_programs
- ⬜ program_applications
- ⬜ transactions
- ⬜ messages
- ⬜ notifications
- ⬜ reviews
- ⬜ analytics_logs

---

### **T3. Security & Compliance**
**Priority**: P0 | **Effort**: 13 points | **Sprint**: 4

**Tasks**:
- ⬜ Data Privacy Act compliance
- ⬜ HTTPS/SSL implementation
- ⬜ Input validation and sanitization
- ⬜ XSS and CSRF protection
- ⬜ SQL injection prevention
- ⬜ Regular security audits
- ⬜ GDPR-compliant data handling
- ⬜ Audit logs for admin actions

---

### **T4. Testing & Quality Assurance**
**Priority**: P1 | **Effort**: 21 points | **Ongoing**

**Tasks**:
- ⬜ Unit tests (80% coverage target)
- ⬜ Integration tests
- ⬜ End-to-end tests
- ⬜ Performance testing
- ⬜ Security testing
- ⬜ User acceptance testing (UAT)
- ⬜ Browser compatibility testing

---

### **T5. DevOps & Deployment**
**Priority**: P1 | **Effort**: 13 points | **Sprint**: 8

**Tasks**:
- ⬜ CI/CD pipeline setup
- ⬜ Staging environment
- ⬜ Production deployment
- ⬜ Database backup strategy
- ⬜ Monitoring and logging (Sentry, LogRocket)
- ⬜ Performance monitoring
- ⬜ Auto-scaling configuration

---

### **T6. Performance Optimization**
**Priority**: P2 | **Effort**: 13 points | **Sprint**: 15

**Tasks**:
- ⬜ Code splitting and lazy loading
- ⬜ Image optimization and CDN
- ⬜ Database query optimization
- ⬜ Caching strategy (Redis)
- ⬜ Bundle size reduction
- ⬜ Lighthouse score >90

---

## **STAKEHOLDER REQUIREMENTS**

### **Government Integration Requirements**
- Integration with DA Price Monitoring System
- Integration with BFAR Fisheries Database
- Integration with LGU procurement systems
- Data sharing protocols with DA-RFO II
- Compliance with government reporting standards

---

### **Accessibility Requirements**
- WCAG 2.1 AA compliance
- Screen reader compatibility
- Keyboard navigation support
- High contrast mode
- Text resizing support

---

## **SUCCESS METRICS (KPIs)**

### **User Adoption**
- Target: 500 producers registered (Year 1)
- Target: 50 institutional buyers registered (Year 1)
- Target: 70% monthly active user rate

### **Transaction Volume**
- Target: ₱10M GMV (Gross Merchandise Value) in Year 1
- Target: 1,000 orders fulfilled (Year 1)
- Target: Average order value: ₱10,000

### **Platform Impact**
- Reduce middleman markup by 30%
- Increase producer income by 25%
- Reduce procurement time by 50%
- 80% buyer-producer match success rate

### **Technical Performance**
- 99.5% uptime
- Page load time <3 seconds
- Mobile responsiveness: 100%
- Zero critical security vulnerabilities

---

## **SPRINT PLANNING SUMMARY**

| Sprint | Duration | Focus | Key Deliverables |
|--------|----------|-------|------------------|
| 1 | 2 weeks | Foundation | Authentication, Database, Basic UI |
| 2 | 2 weeks | Core Features | Producer Listings, Buyer Demand |
| 3 | 2 weeks | Transactions | Order Management, Admin Dashboard |
| 4-5 | 4 weeks | AI Features | Program Matching (NLP) |
| 5-6 | 4 weeks | AI Features | Enterprise Recommendations |
| 6 | 2 weeks | Market Data | Price Insights |
| 7 | 2 weeks | Analytics | Supply-Demand Forecasting |
| 7-8 | 4 weeks | Mobile | Responsive Optimization |
| 9+ | Ongoing | Advanced | Messaging, Payments, Logistics |

---

## **DEPENDENCIES & RISKS**

### **Critical Dependencies**
1. Supabase backend setup
2. Government API access (DA, BFAR)
3. Payment gateway approval
4. Mobile network coverage in Aparri

### **Key Risks**
1. **Low internet penetration** - Mitigation: Offline-first features, SMS fallback
2. **Producer digital literacy** - Mitigation: Training programs, simplified UI
3. **Government API limitations** - Mitigation: Manual data entry fallback
4. **Data privacy concerns** - Mitigation: Transparent privacy policy, secure infrastructure
5. **Market adoption** - Mitigation: Pilot program with cooperatives, incentives

---

## **REVISION HISTORY**

| Date | Version | Changes | Author |
|------|---------|---------|--------|
| 2026-02-15 | 1.0 | Initial backlog creation | Product Team |

---

**Next Steps**:
1. Review and validate with stakeholders
2. Refine effort estimates with development team
3. Prioritize Phase 1 items for MVP sprint planning
4. Schedule stakeholder demos after Sprint 3
5. Begin Supabase backend setup (Sprint 1)
