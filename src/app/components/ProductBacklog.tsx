export function ProductBacklog() {
  return (
    <div className="min-h-screen bg-muted py-12 px-4 sm:px-6 lg:px-8">
      <div className="max-w-7xl mx-auto space-y-8">

        {/* High Priority Section */}
        <div className="bg-card rounded-lg shadow-lg p-8 border-t-4 border-red-500">
          <div className="flex items-center justify-between mb-8">
            <h1 className="text-4xl font-serif text-red-600">
              High Priority Modules for HarborAI
            </h1>
            <div className="w-16 h-16 rounded-full bg-gradient-to-br from-red-500 to-red-700 shadow-xl"></div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
            {/* Module 1 */}
            <div>
              <h3 className="font-semibold text-lg mb-3 text-foreground">Backend API Development</h3>
              <ul className="space-y-2 text-sm text-muted-foreground">
                <li>• Supabase setup</li>
                <li>• Database schema design</li>
                <li>• RESTful API endpoints</li>
                <li>• Authentication (JWT)</li>
                <li>• File storage system</li>
              </ul>
            </div>

            {/* Module 2 */}
            <div>
              <h3 className="font-semibold text-lg mb-3 text-foreground">Authentication & Authorization</h3>
              <ul className="space-y-2 text-sm text-muted-foreground">
                <li>• Email verification</li>
                <li>• Password reset</li>
                <li>• Role-based access</li>
                <li>• Producer approval workflow</li>
                <li>• Session management</li>
              </ul>
            </div>

            {/* Module 3 */}
            <div>
              <h3 className="font-semibold text-lg mb-3 text-foreground">Order Management System</h3>
              <ul className="space-y-2 text-sm text-muted-foreground">
                <li>• Order status tracking</li>
                <li>• Producer acceptance/rejection</li>
                <li>• Delivery scheduling</li>
                <li>• Order notifications</li>
                <li>• Order analytics</li>
              </ul>
            </div>

            {/* Module 4 */}
            <div>
              <h3 className="font-semibold text-lg mb-3 text-foreground">Producer-Buyer Matching</h3>
              <ul className="space-y-2 text-sm text-muted-foreground">
                <li>• Matching algorithm</li>
                <li>• Demand-supply pairing</li>
                <li>• Notification system</li>
                <li>• Bid submission</li>
                <li>• Opportunity tracking</li>
              </ul>
            </div>
          </div>
        </div>

        {/* Medium Priority Section */}
        <div className="bg-card rounded-lg shadow-lg p-8 border-t-4 border-yellow-500">
          <div className="flex items-center justify-between mb-8">
            <h1 className="text-4xl font-serif text-yellow-600">
              Medium Priority – AI & Intelligence Modules
            </h1>
            <p className="mt-2 text-sm text-muted-foreground">
              Planned roadmap items. These are not current features and are not yet implemented in HarborAI.
            </p>
            <div className="w-16 h-16 rounded-full bg-gradient-to-br from-yellow-500 to-yellow-700 shadow-xl"></div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
            {/* Module 1 */}
            <div>
              <h3 className="font-semibold text-lg mb-3 text-foreground">Market Intelligence</h3>
              <ul className="space-y-2 text-sm text-muted-foreground">
                <li>• NLP algorithm</li>
                <li>• Eligibility scoring</li>
                <li>• Profile analysis</li>
                <li>• Application tracking</li>
                <li>• Document upload</li>
              </ul>
            </div>

            {/* Module 2 */}
            <div>
              <h3 className="font-semibold text-lg mb-3 text-foreground">Market Insights</h3>
              <ul className="space-y-2 text-sm text-muted-foreground">
                <li>• ML insight model</li>
                <li>• Weather data integration</li>
                <li>• Market trend analysis</li>
                <li>• Seasonal optimization</li>
                <li>• ROI projections</li>
              </ul>
            </div>

            {/* Module 3 */}
            <div>
              <h3 className="font-semibold text-lg mb-3 text-foreground">Smart Pricing Prompts &amp; Forecasting</h3>
              <ul className="space-y-2 text-sm text-muted-foreground">
                <li>• DA Price API integration</li>
                <li>• Real-time price updates</li>
                <li>• Regional comparisons</li>
                <li>• AI pricing prompts</li>
                <li>• Price alerts</li>
              </ul>
              <p className="mt-3 text-xs leading-5 text-muted-foreground">
                Planned roadmap only. Today&apos;s Smart Pricing Prompts screen is advisory: it shows verified market reference data
                and questions, sets no price, and does no forecasting or ROI projection.
              </p>
            </div>

            {/* Module 4 */}
            <div>
              <h3 className="font-semibold text-lg mb-3 text-foreground">Supply-Demand Analytics</h3>
              <ul className="space-y-2 text-sm text-muted-foreground">
                <li>• Predictive analytics</li>
                <li>• Gap analysis</li>
                <li>• Regional heatmaps</li>
                <li>• Demand forecasting</li>
                <li>• Automated reports</li>
              </ul>
            </div>
          </div>
        </div>

        {/* Low Priority Section */}
        <div className="bg-card rounded-lg shadow-lg p-8 border-t-4 border-green-500">
          <div className="flex items-center justify-between mb-8">
            <h1 className="text-4xl font-serif text-green-600">
              Low Priority Enhancements for User Experience
            </h1>
            <div className="w-16 h-16 rounded-full bg-gradient-to-br from-green-500 to-green-700 shadow-xl"></div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
            {/* Module 1 */}
            <div>
              <h3 className="font-semibold text-lg mb-3 text-foreground">In-App Messaging</h3>
              <ul className="space-y-2 text-sm text-muted-foreground">
                <li>• Real-time chat interface</li>
                <li>• Message notifications</li>
                <li>• Attachment support</li>
                <li>• Read receipts</li>
                <li>• Multi-language translation</li>
              </ul>
            </div>

            {/* Module 2 */}
            <div>
              <h3 className="font-semibold text-lg mb-3 text-foreground">Ratings & Reviews</h3>
              <ul className="space-y-2 text-sm text-muted-foreground">
                <li>• Star rating system</li>
                <li>• Written reviews</li>
                <li>• Review moderation</li>
                <li>• Rating badges</li>
                <li>• Response capability</li>
              </ul>
            </div>

            {/* Module 3 */}
            <div>
              <h3 className="font-semibold text-lg mb-3 text-foreground">Mobile PWA Features</h3>
              <ul className="space-y-2 text-sm text-muted-foreground">
                <li>• Offline capability</li>
                <li>• Mobile camera integration</li>
                <li>• Push notifications</li>
                <li>• Touch optimizations</li>
                <li>• App installation</li>
              </ul>
            </div>

            {/* Module 4 */}
            <div>
              <h3 className="font-semibold text-lg mb-3 text-foreground">Cooperative Management</h3>
              <ul className="space-y-2 text-sm text-muted-foreground">
                <li>• Cooperative profiles</li>
                <li>• Member management</li>
                <li>• Aggregated listings</li>
                <li>• Revenue distribution</li>
                <li>• Bulk ordering</li>
              </ul>
            </div>
          </div>
        </div>

        {/* Additional Priority Section - Advanced Features */}
        <div className="bg-card rounded-lg shadow-lg p-8 border-t-4 border-purple-500">
          <div className="flex items-center justify-between mb-8">
            <h1 className="text-4xl font-serif text-purple-600">
              Future Enhancements – Advanced Integration
            </h1>
            <div className="w-16 h-16 rounded-full bg-gradient-to-br from-purple-500 to-purple-700 shadow-xl"></div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
            {/* Module 1 */}
            <div>
              <h3 className="font-semibold text-lg mb-3 text-foreground">Payment Integration</h3>
              <ul className="space-y-2 text-sm text-muted-foreground">
                <li>• GCash/PayMaya integration</li>
                <li>• Bank transfer support</li>
                <li>• Escrow system</li>
                <li>• Automated invoicing</li>
                <li>• Transaction fees</li>
              </ul>
            </div>

            {/* Module 2 */}
            <div>
              <h3 className="font-semibold text-lg mb-3 text-foreground">Logistics Tracking</h3>
              <ul className="space-y-2 text-sm text-muted-foreground">
                <li>• GPS-based tracking</li>
                <li>• Delivery milestones</li>
                <li>• Proof of delivery</li>
                <li>• Digital signatures</li>
                <li>• Performance metrics</li>
              </ul>
            </div>

            {/* Module 3 */}
            <div>
              <h3 className="font-semibold text-lg mb-3 text-foreground">Quality Certification</h3>
              <ul className="space-y-2 text-sm text-muted-foreground">
                <li>• Certificate verification</li>
                <li>• QR code generation</li>
                <li>• Batch tracking</li>
                <li>• Traceability system</li>
                <li>• Standards integration</li>
              </ul>
            </div>

            {/* Module 4 */}
            <div>
              <h3 className="font-semibold text-lg mb-3 text-foreground">IoT & Satellite Data</h3>
              <ul className="space-y-2 text-sm text-muted-foreground">
                <li>• IoT sensor integration</li>
                <li>• Satellite imagery</li>
                <li>• Crop health monitoring</li>
                <li>• Yield estimation</li>
                <li>• Disaster assessment</li>
              </ul>
            </div>
          </div>
        </div>

        {/* Technical Infrastructure Section */}
        <div className="bg-card rounded-lg shadow-lg p-8 border-t-4 border-blue-500">
          <div className="flex items-center justify-between mb-8">
            <h1 className="text-4xl font-serif text-blue-600">
              Technical Infrastructure & Security
            </h1>
            <div className="w-16 h-16 rounded-full bg-gradient-to-br from-blue-500 to-blue-700 shadow-xl"></div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
            {/* Module 1 */}
            <div>
              <h3 className="font-semibold text-lg mb-3 text-foreground">Security & Compliance</h3>
              <ul className="space-y-2 text-sm text-muted-foreground">
                <li>• Data Privacy Act compliance</li>
                <li>• HTTPS/SSL implementation</li>
                <li>• XSS/CSRF protection</li>
                <li>• SQL injection prevention</li>
                <li>• Security audits</li>
              </ul>
            </div>

            {/* Module 2 */}
            <div>
              <h3 className="font-semibold text-lg mb-3 text-foreground">Testing & QA</h3>
              <ul className="space-y-2 text-sm text-muted-foreground">
                <li>• Unit tests (80% coverage)</li>
                <li>• Integration tests</li>
                <li>• E2E testing</li>
                <li>• Performance testing</li>
                <li>• Browser compatibility</li>
              </ul>
            </div>

            {/* Module 3 */}
            <div>
              <h3 className="font-semibold text-lg mb-3 text-foreground">DevOps & Deployment</h3>
              <ul className="space-y-2 text-sm text-muted-foreground">
                <li>• CI/CD pipeline</li>
                <li>• Staging environment</li>
                <li>• Database backups</li>
                <li>• Monitoring & logging</li>
                <li>• Auto-scaling</li>
              </ul>
            </div>

            {/* Module 4 */}
            <div>
              <h3 className="font-semibold text-lg mb-3 text-foreground">Performance Optimization</h3>
              <ul className="space-y-2 text-sm text-muted-foreground">
                <li>• Code splitting & lazy loading</li>
                <li>• Image optimization & CDN</li>
                <li>• Caching strategy (Redis)</li>
                <li>• Bundle size reduction</li>
                <li>• Lighthouse score &gt;90</li>
              </ul>
            </div>
          </div>
        </div>

        {/* Success Metrics Section */}
        <div className="bg-gradient-to-br from-gray-800 to-gray-900 rounded-lg shadow-lg p-8 text-white">
          <h2 className="text-3xl font-serif mb-6">Success Metrics & KPIs</h2>

          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div className="bg-card/10 rounded-lg p-6 backdrop-blur">
              <h4 className="font-semibold mb-3 text-yellow-400">User Adoption</h4>
              <ul className="space-y-1 text-sm">
                <li>• 500 producers (Year 1)</li>
                <li>• 50 institutional buyers</li>
                <li>• 70% monthly active rate</li>
              </ul>
            </div>

            <div className="bg-card/10 rounded-lg p-6 backdrop-blur">
              <h4 className="font-semibold mb-3 text-green-400">Transaction Volume</h4>
              <ul className="space-y-1 text-sm">
                <li>• ₱10M GMV (Year 1)</li>
                <li>• 1,000 orders fulfilled</li>
                <li>• Avg order: ₱10,000</li>
              </ul>
            </div>

            <div className="bg-card/10 rounded-lg p-6 backdrop-blur">
              <h4 className="font-semibold mb-3 text-blue-400">Platform Impact</h4>
              <ul className="space-y-1 text-sm">
                <li>• 30% reduce middleman fees</li>
                <li>• 25% increase income</li>
                <li>• 80% match success rate</li>
              </ul>
            </div>

            <div className="bg-card/10 rounded-lg p-6 backdrop-blur">
              <h4 className="font-semibold mb-3 text-purple-400">Technical Performance</h4>
              <ul className="space-y-1 text-sm">
                <li>• 99.5% uptime</li>
                <li>• &lt;3 sec page load</li>
                <li>• Zero critical vulnerabilities</li>
              </ul>
            </div>
          </div>
        </div>

      </div>
    </div>
  );
}