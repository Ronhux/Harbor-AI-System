import { DashboardText, useDashboardCopy } from '../../../i18n/DashboardCopy';
import PageHeader from '../../../components/dashboard/PageHeader';
import { useEffect, useMemo, useState } from 'react';
import { Dialog, DialogContent, DialogTitle } from '../../../components/ui/dialog';
import { Calendar, ChevronLeft, ChevronRight, ClipboardList, Download, Eye, FilePlus2, Loader2, Printer, X } from 'lucide-react';
import { toast } from 'sonner';
import { request } from '../../../../lib/api';
import { Badge } from '../../../components/ui/badge';
import { Button } from '../../../components/ui/button';
import { Card, CardContent } from '../../../components/ui/card';

type ReportType = 'producer_activity' | 'supply_demand' | 'demand_summary' | 'order_transactions';
type ExportFormat = 'csv' | 'xlsx' | 'pdf';
type ReportColumn = { key: string; label: string };
type SummaryTable = {
  key: string;
  title: string;
  columns: ReportColumn[];
  rows: Record<string, unknown>[];
};
type ReportPresentation = {
  metadata: Array<{ label: string; value: string }>;
  executive_summary: string;
  purpose_scope: string;
  highlights: Array<{ key: string; label: string; value: unknown; display_value: string }>;
  summary_tables: SummaryTable[];
  findings: string[];
  analysis: string[];
  limitations: string[];
  conclusion: string;
  recommendations: string;
  approval: { prepared_by: string; prepared_by_role: string; reviewed_by: string; approved_by: string };
};
type ReportSnapshot = {
  report_type: ReportType;
  title: string;
  period: string;
  reporting_period: { start_date: string; end_date: string; timezone: string; date_basis: string; boundaries: string };
  generated_at: string;
  filters: Record<string, string>;
  definitions: Record<string, string>;
  prepared_by: string;
  prepared_by_role: string;
  report_reference: string;
  presentation: ReportPresentation;
  columns: ReportColumn[];
  summary: Record<string, unknown>;
  row_count: number;
  rows: Record<string, unknown>[];
  pagination: { page: number; per_page: number; total_rows: number; last_page: number };
};
type HistoryReport = {
  id: number;
  type: ReportType;
  title: string;
  start_date: string;
  end_date: string;
  filters: Record<string, string>;
  generated_at: string;
  size: number;
  formats: ExportFormat[];
};
type HistoryResponse = { data: HistoryReport[]; pagination: { page: number; per_page: number; total_rows: number; last_page: number } };

type ReportOption = { value: ReportType; label: string; description: string };
const REPORT_OPTIONS: ReportOption[] = [
  { value: 'producer_activity', label: 'Producer Activity', description: 'Producer accounts created in the selected period and listings they created during the same period.' },
  { value: 'supply_demand', label: 'Supply-Demand', description: 'Active verified supply and active demand, grouped separately by exact product and unit.' },
  { value: 'demand_summary', label: 'Demand Summary', description: 'Persisted institutional buyer requests created in the selected period.' },
  { value: 'order_transactions', label: 'Order Transactions', description: 'Persisted orders by order date with current workflow/payment status. Stored order amount is not realized revenue.' },
];
const todayUtc = () => new Date().toISOString().slice(0, 10);
const dateTextUtc = (date: Date) => `${date.getUTCFullYear()}-${String(date.getUTCMonth() + 1).padStart(2, '0')}-${String(date.getUTCDate()).padStart(2, '0')}`;
const firstDayOfCurrentMonth = () => {
  const date = new Date(`${todayUtc()}T00:00:00Z`);
  date.setUTCDate(1);
  return dateTextUtc(date);
};
const emptyPagination = { page: 1, per_page: 25, total_rows: 0, last_page: 1 };
const humanize = (value: string) => value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
const displayValue = (value: unknown, key = '') => {
  if (value === null || value === undefined || value === '') return '—';
  if (typeof value === 'object') return 'See structured report table';
  if (typeof value === 'number') {
    if (!Number.isFinite(value)) return 'Not available';
    return key.includes('amount') || key.includes('price')
      ? new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(value)
      : new Intl.NumberFormat('en-PH', { maximumFractionDigits: 2 }).format(value);
  }
  return String(value);
};
const tableMarkup = (columns: ReportColumn[], rows: Record<string, unknown>[], emptyText = 'No records found.') => {
  const header = columns.map((column) => `<th>${escapeHtml(column.label)}</th>`).join('');
  const body = rows.length === 0
    ? `<tr><td colspan="${Math.max(columns.length, 1)}" class="empty">${escapeHtml(emptyText)}</td></tr>`
    : rows.map((row) => `<tr>${columns.map((column) => `<td>${escapeHtml(displayValue(row[column.key], column.key))}</td>`).join('')}</tr>`).join('');
  return `<table><thead><tr>${header}</tr></thead><tbody>${body}</tbody></table>`;
};
const escapeHtml = (value: unknown) => String(value ?? '')
  .replace(/&/g, '&amp;')
  .replace(/</g, '&lt;')
  .replace(/>/g, '&gt;')
  .replace(/"/g, '&quot;')
  .replace(/'/g, '&#039;');

export default function Reports() {
  const copy = useDashboardCopy();
  const [history, setHistory] = useState<HistoryReport[]>([]);
  const [historyPage, setHistoryPage] = useState(1);
  const [historyPagination, setHistoryPagination] = useState(emptyPagination);
  const [historyLoading, setHistoryLoading] = useState(true);
  const [historyError, setHistoryError] = useState('');
  const [open, setOpen] = useState(false);
  const [type, setType] = useState<ReportType>('supply_demand');
  const [period, setPeriod] = useState('monthly');
  const today = todayUtc();
  const [startDate, setStartDate] = useState(firstDayOfCurrentMonth);
  const [endDate, setEndDate] = useState(today);
  const [product, setProduct] = useState('');
  const [location, setLocation] = useState('');
  const [status, setStatus] = useState('');
  const [preview, setPreview] = useState<ReportSnapshot | null>(null);
  const [previewError, setPreviewError] = useState('');
  const [previewBusy, setPreviewBusy] = useState(false);
  const [generateBusy, setGenerateBusy] = useState(false);
  const [printing, setPrinting] = useState(false);
  const [savedReportId, setSavedReportId] = useState<number | null>(null);
  const [downloading, setDownloading] = useState<string | null>(null);

  const selectedOption = REPORT_OPTIONS.find((option) => option.value === type) ?? REPORT_OPTIONS[0];
  const supportsLocation = type !== 'order_transactions';
  const supportsStatus = type !== 'supply_demand';
  const filters = useMemo(() => ({
    ...(product.trim() ? { product: product.trim() } : {}),
    ...(supportsLocation && location.trim() ? { location: location.trim() } : {}),
    ...(supportsStatus && status.trim() ? { status: status.trim() } : {}),
  }), [product, location, status, supportsLocation, supportsStatus]);
  const params = useMemo(() => ({ type, start_date: startDate, end_date: endDate, filters, page: 1, per_page: 25 }), [type, startDate, endDate, filters]);

  const loadHistory = async (page = historyPage) => {
    setHistoryLoading(true);
    setHistoryError('');
    try {
      const result = await request(`/api/admin/reports?page=${page}&per_page=10`) as HistoryResponse;
      setHistory(result.data ?? []);
      setHistoryPagination(result.pagination ?? emptyPagination);
      setHistoryPage(result.pagination?.page ?? page);
    } catch (error) {
      setHistoryError(error instanceof Error ? error.message : 'Could not load saved reports.');
    } finally {
      setHistoryLoading(false);
    }
  };

  useEffect(() => { void loadHistory(1); }, []);

  const selectPeriod = (value: string) => {
    setPeriod(value);
    const end = new Date(`${todayUtc()}T00:00:00Z`);
    const start = new Date(end);
    if (value === 'weekly') start.setUTCDate(start.getUTCDate() - 6);
    if (value === 'monthly') start.setUTCDate(1);
    if (value === 'yearly') { start.setUTCMonth(0, 1); }
    setStartDate(dateTextUtc(start));
    setEndDate(dateTextUtc(end));
  };

  const openNewReport = () => {
    setPreview(null);
    setPreviewError('');
    setSavedReportId(null);
    setOpen(true);
  };

  const runPreview = async (page = 1) => {
    setPreviewBusy(true);
    setPreviewError('');
    setPreview(null);
    try {
      const result = await request('/api/admin/reports/preview', {
        method: 'POST',
        body: { ...params, page, per_page: 25 },
      }) as ReportSnapshot;
      setPreview(result);
      setSavedReportId(null);
    } catch (error) {
      setPreviewError(error instanceof Error ? error.message : 'Could not preview this report.');
    } finally {
      setPreviewBusy(false);
    }
  };

  const generate = async () => {
    setGenerateBusy(true);
    setPreviewError('');
    setPreview(null);
    try {
      const result = await request('/api/admin/reports', { method: 'POST', body: params }) as { id: number; report: ReportSnapshot };
      setPreview(result.report);
      setSavedReportId(result.id);
      await loadHistory(1);
      toast.success('Report snapshot saved.');
    } catch (error) {
      setPreviewError(error instanceof Error ? error.message : 'Report generation failed.');
    } finally {
      setGenerateBusy(false);
    }
  };

  const openSavedReport = async (id: number, page = 1) => {
    setPreviewBusy(true);
    setPreviewError('');
    setPreview(null);
    setSavedReportId(id);
    setOpen(true);
    try {
      const result = await request(`/api/admin/reports/${id}?page=${page}&per_page=25`) as ReportSnapshot;
      setPreview(result);
    } catch (error) {
      setPreviewError(error instanceof Error ? error.message : 'This saved report is unavailable.');
    } finally {
      setPreviewBusy(false);
    }
  };

  const download = async (id: number, format: ExportFormat) => {
    const downloadKey = `${id}-${format}`;
    setDownloading(downloadKey);
    try {
      const token = localStorage.getItem('authToken');
      const path = format === 'csv' ? `/api/admin/reports/${id}/download` : `/api/admin/reports/${id}/download/${format}`;
      const response = await fetch(path, { credentials: 'include', headers: token ? { Authorization: `Bearer ${token}` } : {} });
      if (!response.ok) {
        if (response.status === 404 || response.status === 403) throw new Error('Hindi available ang report na ito para sa account mo.');
        throw new Error('Hindi ma-download ang report. Subukan ulit.');
      }
      const blob = await response.blob();
      const url = URL.createObjectURL(blob);
      const anchor = document.createElement('a');
      anchor.href = url;
      anchor.download = `harborai-report-${id}.${format}`;
      anchor.click();
      window.setTimeout(() => URL.revokeObjectURL(url), 1000);
    } catch (error) {
      toast.error(error instanceof Error ? error.message : 'Report download failed.');
    } finally {
      setDownloading(null);
    }
  };

  const printReport = async () => {
    if (!preview) return;

    const printWindow = window.open('', '_blank', 'width=1000,height=900');

    if (!printWindow) {
      toast.error('The browser blocked the print window. Please allow pop-ups and try again.');
      return;
    }

    printWindow.opener = null;
    setPrinting(true);
    printWindow.document.write('<!doctype html><title>Preparing report</title><p style="font:16px Arial,sans-serif;padding:24px">Preparing the complete report for printing…</p>');
    printWindow.document.close();

    try {
      let printableReport = preview;
      const firstPage = await request(savedReportId
        ? `/api/admin/reports/${savedReportId}?page=1&per_page=100`
        : '/api/admin/reports/preview', savedReportId ? undefined : {
          method: 'POST',
          body: { ...params, page: 1, per_page: 100 },
        }) as ReportSnapshot;
      printableReport = { ...firstPage, rows: [...firstPage.rows] };

      for (let page = 2; page <= firstPage.pagination.last_page; page += 1) {
        if (printWindow.closed) return;
        const nextPage = await request(savedReportId
          ? `/api/admin/reports/${savedReportId}?page=${page}&per_page=100`
          : '/api/admin/reports/preview', savedReportId ? undefined : {
            method: 'POST',
            body: { ...params, page, per_page: 100 },
          }) as ReportSnapshot;
        printableReport.rows.push(...nextPage.rows);
      }

      if (printWindow.closed) return;

      const presentation = printableReport.presentation;
      const metadataRows = presentation.metadata.map((item) => `<tr><th>${escapeHtml(item.label)}</th><td>${escapeHtml(item.value)}</td></tr>`).join('');
      const highlights = presentation.highlights.length === 0
        ? '<p>No summary metrics were calculated.</p>'
        : tableMarkup(
          [{ key: 'label', label: 'Measured Metric' }, { key: 'display_value', label: 'Value' }],
          presentation.highlights.map((item) => ({ label: item.label, display_value: item.display_value })),
        );
      const summaryTables = presentation.summary_tables.map((table) => `<h3>${escapeHtml(table.title)}</h3>${tableMarkup(table.columns, table.rows)}`).join('');
      const listMarkup = (items: string[]) => `<ul>${items.map((item) => `<li>${escapeHtml(item)}</li>`).join('')}</ul>`;
      const detailTable = printableReport.rows.length === 0
        ? '<p class="empty">No records matched this report and filter set.</p>'
        : tableMarkup(printableReport.columns, printableReport.rows, 'No records matched this report and filter set.');
      const logoUrl = new URL('/logo.png', window.location.origin).toString();

      printWindow.document.open();
      printWindow.document.write(`<!doctype html>
      <html lang="en">
      <head>
        <meta charset="utf-8" />
        <title>${escapeHtml(printableReport.title)}</title>
        <style>
          @page { size: letter landscape; margin: 14mm; }
          :root { color-scheme: light; }
          * { box-sizing: border-box; }
          body {
            margin: 0;
            padding: 20px;
            font-family: Arial, Helvetica, sans-serif;
            color: #123C5C;
            background: #ffffff;
            line-height: 1.5;
          }
          .masthead { display:flex; align-items:center; gap:16px; padding-bottom:12px; border-bottom:3px solid #0F9488; text-align:center; }
          .masthead img { width:72px; height:72px; object-fit:contain; }
          .identity { flex:1; }
          .place { color:#45586B; font-size:12px; }
          .brand { margin:3px 0; font-size:24px; font-weight:800; letter-spacing:2px; }
          .tagline { color:#0F766E; font-size:10px; font-weight:bold; }
          .report-label { margin-top:6px; color:#45586B; font-size:10px; font-weight:bold; }
          h1 { margin:14px 0 3px; text-align:center; font-size:24px; }
          .period { margin:0 0 14px; text-align:center; color:#45586B; }
          h2 { margin:18px 0 8px; padding:7px 10px; background:#123C5C; color:white; font-size:15px; page-break-after:avoid; }
          h3 { margin:14px 0 5px; color:#0F766E; font-size:13px; }
          p, li { font-size:12px; }
          ul { padding-left:20px; }
          table { width:100%; border-collapse:collapse; table-layout:auto; margin:8px 0 12px; page-break-inside:auto; }
          thead { display:table-header-group; }
          tr { page-break-inside:avoid; }
          th, td { border:1px solid #C9D4DA; padding:6px 8px; text-align:left; vertical-align:top; font-size:10px; overflow-wrap:anywhere; }
          th { background:#123C5C; color:#fff; }
          .metadata th { width:18%; color:#123C5C; background:#EAF4F5; }
          .metadata td { width:32%; }
          .records { page-break-before:always; }
          .empty { padding:18px; text-align:center; color:#60717D; font-style:italic; }
          .approval td { width:33.33%; height:110px; vertical-align:top; }
          .signature { display:block; margin-top:38px; border-top:1px solid #94A3B8; padding-top:5px; }
          .control { margin-top:14px; padding:9px 12px; border-left:4px solid #0F9488; background:#F3F7F8; color:#45586B; font-size:10px; }
          .footer { position:fixed; bottom:-8mm; left:0; right:0; padding-top:6px; border-top:1px solid #D8D3C4; text-align:center; color:#60717D; font-size:9px; }
          @media print {
            html, body { margin: 0; }
            body { padding: 0; }
            h2, th { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
          }
        </style>
      </head>
      <body>
        <header class="masthead"><img src="${escapeHtml(logoUrl)}" alt="HarborAI logo"><div class="identity">
          <div class="place">Republic of the Philippines · Province of Cagayan · Municipality of Aparri</div>
          <div class="brand">HARBORAI</div><div class="tagline">SMART TRADING AND ENTERPRISE PLATFORM FOR AGRICULTURE IN APARRI</div>
          <div class="report-label">ADMINISTRATIVE REPORT · EDITABLE SYSTEM REPORT, NOT OFFICIAL LGU LETTERHEAD</div>
        </div></header>
        <h1>${escapeHtml(printableReport.title)}</h1><p class="period">${escapeHtml(printableReport.period)}</p>
        <table class="metadata"><tbody>${metadataRows}</tbody></table>
        <section><h2>1. Executive Summary</h2><p>${escapeHtml(presentation.executive_summary)}</p></section>
        <section><h2>2. Purpose and Scope</h2><p>${escapeHtml(presentation.purpose_scope)}</p></section>
        <section><h2>3. Key Highlights</h2>${highlights}${summaryTables}</section>
        <section><h2>4. Findings and Observations</h2>${listMarkup(presentation.findings)}</section>
        <section class="records"><h2>5. Detailed Records</h2><p>Report-specific records returned by the selected query. Total records: ${escapeHtml(displayValue(printableReport.row_count))}.</p>${detailTable}</section>
        <section><h2>6. Analysis and Interpretation</h2>${listMarkup(presentation.analysis)}</section>
        <section><h2>7. Issues, Limitations, and Data Notes</h2>${listMarkup(presentation.limitations)}</section>
        <section><h2>8. Conclusion</h2><p>${escapeHtml(presentation.conclusion)}</p></section>
        <section><h2>9. Recommendations / Next Steps</h2><p>${escapeHtml(presentation.recommendations)}</p></section>
        <section><h2>10. Review and Approval</h2><table class="approval"><thead><tr><th>PREPARED BY</th><th>REVIEWED BY</th><th>APPROVED BY</th></tr></thead><tbody><tr>
          <td>${escapeHtml(presentation.approval.prepared_by)}<br>${escapeHtml(presentation.approval.prepared_by_role)}<span class="signature">Name / Position / Date</span></td>
          <td><span class="signature">Name / Position / Date</span></td><td><span class="signature">Name / Position / Date</span></td>
        </tr></tbody></table></section>
        <div class="control"><strong>DOCUMENT CONTROL NOTE.</strong> This is an editable HarborAI report template inspired by formal administrative report layouts. It is not an official Municipality of Aparri form or letterhead. Use official seals, logos, and signatories only when authorized. Verify all figures against system data before distribution.</div>
        <footer class="footer">HARBORAI · ADMINISTRATIVE REPORT · System-generated; not an approval or official LGU-issued document.</footer>
      </body>
      </html>`);

      printWindow.document.close();
      printWindow.onafterprint = () => printWindow.close();
      printWindow.focus();
      window.setTimeout(() => {
        if (!printWindow.closed) printWindow.print();
      }, 250);
    } catch (error) {
      if (!printWindow.closed) printWindow.close();
      toast.error(error instanceof Error ? error.message : 'Could not prepare the complete report for printing.');
    } finally {
      setPrinting(false);
    }
  };

  const closeDialog = () => {
    if (previewBusy || generateBusy) return;
    setOpen(false);
    setPreview(null);
    setPreviewError('');
  };

  const statusLabel = type === 'producer_activity' ? 'Verification status' : type === 'order_transactions' ? 'Fulfillment status' : 'Request status';
  const busy = previewBusy || generateBusy;

  return <div className="min-w-0 space-y-5 [&_button]:min-h-11">
    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <PageHeader title={copy("Reports")} icon={ClipboardList} subtitle={copy("Generate, review and export your saved administrative reports.")} />
      <Button className="min-h-11 rounded-full bg-[#22C55E] hover:bg-[#15803D]" onClick={openNewReport}><FilePlus2 className="mr-2 h-4 w-4" /><DashboardText text="Generate New Report" /></Button>
    </div>

    {historyError && <Card><CardContent className="p-4 text-sm text-red-700" role="alert">{historyError}<Button variant="outline" className="ml-3 min-h-11" onClick={() => void loadHistory()}><DashboardText text="Retry" /></Button></CardContent></Card>}
    {historyLoading ? <p className="py-8 text-center text-muted-foreground"><DashboardText text="Loading saved reports…" /></p>
      : historyError ? <p className="text-sm text-muted-foreground"><DashboardText text="Saved report history is unavailable." /></p> : history.length === 0 ? <Card><CardContent className="p-8 text-center text-muted-foreground"><DashboardText text="No reports have been generated by this Admin account." /></CardContent></Card>
        : <div className="space-y-3">{history.map((report) => <Card key={report.id} className="border-2 border-border"><CardContent className="flex flex-col gap-4 p-4 lg:flex-row lg:items-center lg:justify-between">
          <div className="flex min-w-0 items-center gap-4"><div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-[#0F9488]/15"><ClipboardList className="h-6 w-6 text-[#0F9488]" /></div><div className="min-w-0"><div className="flex flex-wrap items-center gap-2"><h2 className="font-bold text-foreground">{report.title}</h2><Badge variant="secondary">{humanize(report.type)}</Badge></div><p className="mt-1 text-sm text-muted-foreground">{report.start_date} – {report.end_date}{' '}<DashboardText text="· Generated" />{' '}{new Date(`${report.generated_at.replace(' ', 'T')}Z`).toLocaleString()} · {(report.size / 1024).toFixed(1)} KB</p><p className="mt-1 break-words text-xs text-muted-foreground"><DashboardText text="Filters:" />{' '}{Object.keys(report.filters ?? {}).length ? Object.entries(report.filters).map(([key, value]) => `${humanize(key)}: ${value}`).join(' · ') : copy("None")}</p></div></div>
          <div className="flex flex-wrap gap-2"><Button variant="outline" className="min-h-11" onClick={() => void openSavedReport(report.id)}><Eye className="mr-2 h-4 w-4" /><DashboardText text="Preview" /></Button>{report.formats.map((format) => <Button key={format} variant="outline" className="min-h-11" disabled={downloading !== null} onClick={() => void download(report.id, format)}>{downloading === `${report.id}-${format}` ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Download className="mr-2 h-4 w-4" />}{format.toUpperCase()}</Button>)}</div>
        </CardContent></Card>)}
          <div className="flex flex-wrap items-center justify-between gap-3 text-sm text-muted-foreground"><span>{historyPagination.total_rows}{' '}<DashboardText text="saved reports · Page" />{' '}{historyPagination.page}{' '}<DashboardText text="of" />{' '}{historyPagination.last_page}</span><div className="flex gap-2"><Button variant="outline" disabled={historyPage <= 1 || historyLoading} onClick={() => void loadHistory(historyPage - 1)}><ChevronLeft className="h-4 w-4" /><DashboardText text="Previous" /></Button><Button variant="outline" disabled={historyPage >= historyPagination.last_page || historyLoading} onClick={() => void loadHistory(historyPage + 1)}><DashboardText text="Next" /><ChevronRight className="ml-1 h-4 w-4" /></Button></div></div>
        </div>}

    <Dialog open={open} onOpenChange={value=>{if(!value)closeDialog()}}><DialogContent closeDisabled={previewBusy||generateBusy} showCloseButton={false} aria-describedby={undefined} onEscapeKeyDown={event=>{if(previewBusy||generateBusy)event.preventDefault()}} onInteractOutside={event=>{if(previewBusy||generateBusy)event.preventDefault()}} className="flex w-[min(1100px,calc(100vw-2rem))] max-w-full flex-col overflow-hidden bg-background p-0 sm:max-w-[1100px] max-h-[90dvh]">
          <div className="flex shrink-0 items-start justify-between gap-4 border-b border-border px-5 py-4 pr-14 sm:px-8 sm:py-5 sm:pr-16">
            <div className="min-w-0">
              <DialogTitle className="font-display break-words text-xl leading-tight text-foreground sm:text-2xl">{savedReportId ? copy("Saved Report Snapshot") : copy("Generate New Report")}</DialogTitle>
              <p className="mt-1 text-sm text-muted-foreground">{savedReportId ? copy("This preview and its downloads use the same saved snapshot.") : copy("Choose a supported report, date range, and applicable filters.")}</p>
            </div>
            <button type="button" disabled={previewBusy||generateBusy} aria-label={copy("Close report dialog")} className="min-h-11 min-w-11 shrink-0 rounded-lg p-2 text-foreground hover:bg-card/70" onClick={closeDialog}><X /></button>
          </div>

          <div className="min-h-0 flex-1 overflow-y-auto px-5 py-5 sm:px-8 sm:py-6">

      {!savedReportId && <>
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-[1.5fr_1fr_1fr_1fr]">
          <label className="min-w-0 text-sm font-bold text-foreground"><DashboardText text="Report type" /><select className="mt-1 min-h-11 min-w-0 w-full rounded-lg border border-[#D8D3C4] bg-card px-3" value={type} onChange={(event) => { setType(event.target.value as ReportType); setPreview(null); }}>
            {REPORT_OPTIONS.map((option) => <option key={option.value} value={option.value}>{copy(option.label)}</option>)}
          </select><span className="mt-1 block font-normal text-muted-foreground">{copy(selectedOption.description)}</span></label>
          <label className="min-w-0 text-sm font-bold text-foreground"><DashboardText text="Period" /><select className="mt-1 min-h-11 w-full rounded-lg border border-[#D8D3C4] bg-card px-3" value={period} onChange={(event) => selectPeriod(event.target.value)}>{['daily', 'weekly', 'monthly', 'yearly', 'custom'].map((option) => <option key={option} value={option}>{option[0].toUpperCase() + option.slice(1)}</option>)}</select></label>
          <label className="min-w-0 text-sm font-bold text-foreground"><DashboardText text="Start date" /><input type="date" max={endDate} className="mt-1 min-h-11 w-full rounded-lg border border-[#D8D3C4] bg-card px-2" value={startDate} onChange={(event) => { setStartDate(event.target.value); setPeriod('custom'); setPreview(null); }} /></label>
          <label className="min-w-0 text-sm font-bold text-foreground"><DashboardText text="End date" /><input type="date" min={startDate} max={todayUtc()} className="mt-1 min-h-11 w-full rounded-lg border border-[#D8D3C4] bg-card px-2" value={endDate} onChange={(event) => { setEndDate(event.target.value); setPeriod('custom'); setPreview(null); }} /></label>
        </div>
        <div className={`mt-4 grid gap-4 sm:grid-cols-2 ${supportsLocation && supportsStatus ? 'lg:grid-cols-3' : 'lg:grid-cols-2'}`}>
          <label className="min-w-0 text-sm font-bold text-foreground"><DashboardText text="Product filter" /><input maxLength={120} placeholder={copy("Any product")} className="mt-1 min-h-11 w-full rounded-lg border border-[#D8D3C4] bg-card px-3" value={product} onChange={(event) => { setProduct(event.target.value); setPreview(null); }} /></label>
          {supportsLocation && <label className="min-w-0 text-sm font-bold text-foreground"><DashboardText text="Location filter" /><input maxLength={120} placeholder={copy("Any location")} className="mt-1 min-h-11 w-full rounded-lg border border-[#D8D3C4] bg-card px-3" value={location} onChange={(event) => { setLocation(event.target.value); setPreview(null); }} /></label>}
          {supportsStatus && <label className="min-w-0 text-sm font-bold text-foreground">{statusLabel}<input maxLength={60} placeholder={copy("Any status")} className="mt-1 min-h-11 w-full rounded-lg border border-[#D8D3C4] bg-card px-3" value={status} onChange={(event) => { setStatus(event.target.value); setPreview(null); }} /></label>}
        </div>
        <div className="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end"><Button variant="outline" className="min-h-11" onClick={closeDialog} disabled={busy}><DashboardText text="Close" /></Button><Button variant="outline" className="min-h-11" disabled={busy || !startDate || !endDate || startDate > endDate} onClick={() => void runPreview()}>{previewBusy ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Eye className="mr-2 h-4 w-4" />}{' '}<DashboardText text="Preview" /></Button><Button className="min-h-11 bg-[#0F9488] text-white hover:bg-[#0B7F74]" disabled={busy || !startDate || !endDate || startDate > endDate} onClick={() => void generate()}>{generateBusy ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Calendar className="mr-2 h-4 w-4" />}{' '}<DashboardText text="Generate & Save Snapshot" /></Button></div>
      </>}

      {previewBusy && <p className="py-8 text-center text-muted-foreground"><DashboardText text="Loading report preview…" /></p>}
      {previewError && <Card className="mt-4 border-red-200"><CardContent className="p-4 text-sm text-red-700" role="alert">{previewError}</CardContent></Card>}
      {preview && <div className="mt-5 min-w-0 rounded-xl border border-[#D8D3C4] bg-card p-4 sm:p-6">
        <div className="mb-5 flex flex-col gap-3 border-b-2 border-[#0F9488] pb-4 sm:flex-row sm:items-center">
          <img src="/logo.png" alt="HarborAI" className="h-14 w-14 object-contain" />
          <div className="min-w-0 flex-1 text-center sm:text-left">
            <p className="text-xs text-muted-foreground">Republic of the Philippines · Province of Cagayan · Municipality of Aparri</p>
            <p className="text-xl font-black tracking-[0.16em] text-foreground">HARBORAI</p>
            <p className="text-[10px] font-bold text-[#0F766E]">SMART TRADING AND ENTERPRISE PLATFORM FOR AGRICULTURE IN APARRI</p>
            <p className="mt-1 text-[10px] font-bold text-muted-foreground"><DashboardText text="ADMINISTRATIVE REPORT · NOT OFFICIAL LGU LETTERHEAD" /></p>
          </div>
          <div className="flex flex-wrap gap-2 print:hidden">
            <Button size="sm" variant="outline" disabled={printing} onClick={() => void printReport()}><Printer className="mr-1 h-4 w-4" />{printing ? copy("Preparing…") : copy("Print Report")}</Button>
            {savedReportId && (['csv', 'xlsx', 'pdf'] as ExportFormat[]).map((format) => <Button key={format} size="sm" variant="outline" disabled={downloading !== null} onClick={() => void download(savedReportId, format)}>{downloading === `${savedReportId}-${format}` ? <Loader2 className="mr-1 h-4 w-4 animate-spin" /> : <Download className="mr-1 h-4 w-4" />}{format.toUpperCase()}</Button>)}
          </div>
        </div>
        <div className="text-center">
          <h3 className="text-xl font-bold text-foreground">{preview.title}</h3>
          <p className="mt-1 text-sm text-muted-foreground">{preview.period} · {preview.reporting_period.timezone}</p>
        </div>
        <dl className="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
          {preview.presentation.metadata.map((item) => <div key={item.label} className="rounded-lg border border-border bg-background p-3">
            <dt className="text-[10px] font-bold uppercase tracking-wide text-muted-foreground">{item.label}</dt>
            <dd className="mt-1 break-words text-sm font-semibold text-foreground">{item.value}</dd>
          </div>)}
        </dl>
        <div className="mt-4 flex flex-wrap gap-2">
          <Badge variant="secondary">{humanize(preview.report_type)}</Badge>
          {Object.entries(preview.filters ?? {}).length === 0 ? <Badge variant="secondary"><DashboardText text="No filters" /></Badge> : Object.entries(preview.filters).map(([key, value]) => <Badge key={key} variant="secondary">{humanize(key)}: {value}</Badge>)}
        </div>

        <section className="mt-5">
          <h4 className="mb-2 rounded-md bg-[#123C5C] px-3 py-2 text-sm font-bold text-white"><DashboardText text="1. Executive Summary" /></h4>
          <p className="text-sm leading-6 text-muted-foreground">{preview.presentation.executive_summary}</p>
        </section>
        <section className="mt-4">
          <h4 className="mb-2 rounded-md bg-[#123C5C] px-3 py-2 text-sm font-bold text-white"><DashboardText text="2. Purpose and Scope" /></h4>
          <p className="text-sm leading-6 text-muted-foreground">{preview.presentation.purpose_scope}</p>
        </section>
        <section className="mt-4">
          <h4 className="mb-2 rounded-md bg-[#123C5C] px-3 py-2 text-sm font-bold text-white"><DashboardText text="3. Key Highlights" /></h4>
          {preview.presentation.highlights.length === 0 ? <p className="rounded-lg bg-background p-3 text-sm text-muted-foreground"><DashboardText text="No summary metrics were calculated." /></p>
            : <dl className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">{preview.presentation.highlights.map((item) => <div key={item.key} className="rounded-lg border border-border bg-background p-3">
              <dt className="text-xs font-semibold text-muted-foreground">{item.label}</dt><dd className="mt-1 break-words text-lg font-bold text-foreground">{item.display_value}</dd>
            </div>)}</dl>}
          <div className="mt-3 grid gap-4 lg:grid-cols-2">
            {preview.presentation.summary_tables.map((table) => <div key={table.key} className="min-w-0">
              <h5 className="mb-1 text-sm font-bold text-foreground">{table.title}</h5>
              <div tabIndex={0} role="region" aria-label={copy("Report data table")} className="max-w-full overflow-x-auto rounded-lg border border-[#D8D3C4]">
                <table className="w-full border-collapse text-left text-sm">
                  <thead><tr>{table.columns.map((column) => <th key={column.key} className="bg-[#123C5C] p-2 font-bold text-white">{column.label}</th>)}</tr></thead>
                  <tbody>{table.rows.length === 0 ? <tr><td colSpan={table.columns.length} className="p-3 text-center text-muted-foreground"><DashboardText text="No summary records." /></td></tr>
                    : table.rows.map((row, rowIndex) => <tr key={rowIndex}>{table.columns.map((column) => <td key={column.key} className="border-t border-border p-2 text-muted-foreground">{displayValue(row[column.key], column.key)}</td>)}</tr>)}
                  </tbody>
                </table>
              </div>
            </div>)}
          </div>
        </section>
        <section className="mt-4">
          <h4 className="mb-2 rounded-md bg-[#123C5C] px-3 py-2 text-sm font-bold text-white"><DashboardText text="4. Findings and Observations" /></h4>
          <ul className="list-disc space-y-1 pl-5 text-sm leading-5 text-muted-foreground">{preview.presentation.findings.map((item, index) => <li key={index}>{item}</li>)}</ul>
        </section>
        <section className="mt-4">
          <h4 className="mb-2 rounded-md bg-[#123C5C] px-3 py-2 text-sm font-bold text-white"><DashboardText text="5. Detailed Records" /><span className="font-normal">({preview.row_count.toLocaleString()}{' '}<DashboardText text="total)" /></span></h4>
          {preview.rows.length === 0 ? <p className="rounded-lg bg-background p-4 text-sm text-muted-foreground"><DashboardText text="No records matched this report and filter set." /></p>
            : <div tabIndex={0} role="region" aria-label={copy("Report data table")} className="max-w-full overflow-x-auto rounded-lg border border-[#D8D3C4]"><table className="w-full min-w-[760px] border-collapse text-left text-sm"><thead><tr>{preview.columns.map((column) => <th key={column.key} className="sticky top-0 bg-[#123C5C] p-2 font-bold text-white">{column.label}</th>)}</tr></thead><tbody>{preview.rows.map((row, rowIndex) => <tr key={rowIndex}>{preview.columns.map((column) => <td key={column.key} className="max-w-[360px] break-words border-t border-border p-2 text-muted-foreground">{displayValue(row[column.key], column.key)}</td>)}</tr>)}</tbody></table></div>}
        </section>
        <section className="mt-4">
          <h4 className="mb-2 rounded-md bg-[#123C5C] px-3 py-2 text-sm font-bold text-white"><DashboardText text="6. Analysis and Interpretation" /></h4>
          <ul className="list-disc space-y-1 pl-5 text-sm leading-5 text-muted-foreground">{preview.presentation.analysis.map((item, index) => <li key={index}>{item}</li>)}</ul>
        </section>
        <section className="mt-4">
          <h4 className="mb-2 rounded-md bg-[#123C5C] px-3 py-2 text-sm font-bold text-white"><DashboardText text="7. Issues, Limitations, and Data Notes" /></h4>
          <ul className="list-disc space-y-1 pl-5 text-sm leading-5 text-muted-foreground">{preview.presentation.limitations.map((item, index) => <li key={index}>{item}</li>)}</ul>
        </section>
        <section className="mt-4"><h4 className="mb-2 rounded-md bg-[#123C5C] px-3 py-2 text-sm font-bold text-white"><DashboardText text="8. Conclusion" /></h4><p className="text-sm leading-6 text-muted-foreground">{preview.presentation.conclusion}</p></section>
        <section className="mt-4"><h4 className="mb-2 rounded-md bg-[#123C5C] px-3 py-2 text-sm font-bold text-white"><DashboardText text="9. Recommendations / Next Steps" /></h4><p className="text-sm leading-6 text-muted-foreground">{preview.presentation.recommendations}</p></section>
        <section className="mt-4">
          <h4 className="mb-2 rounded-md bg-[#123C5C] px-3 py-2 text-sm font-bold text-white"><DashboardText text="10. Review and Approval" /></h4>
          <div className="grid gap-2 sm:grid-cols-3"><div className="min-h-28 rounded-lg border border-[#D8D3C4] p-3"><h5 className="font-bold text-foreground"><DashboardText text="PREPARED BY" /></h5><p className="mt-2 text-sm">{preview.presentation.approval.prepared_by}</p><p className="text-xs text-muted-foreground">{preview.presentation.approval.prepared_by_role}</p><div className="mt-6 border-t border-[#94A3B8] pt-1 text-xs text-muted-foreground"><DashboardText text="Name / Position / Date" /></div></div>
            {['REVIEWED BY', 'APPROVED BY'].map((label) => <div key={copy(label)} className="min-h-28 rounded-lg border border-[#D8D3D4] p-3"><h5 className="font-bold text-foreground">{copy(label)}</h5><div className="mt-12 border-t border-[#94A3B8] pt-1 text-xs text-muted-foreground"><DashboardText text="Name / Position / Date" /></div></div>)}</div>
        </section>
        <p className="mt-4 border-l-4 border-[#0F9488] bg-[#F3F7F8] p-3 text-xs leading-5 text-muted-foreground"><strong><DashboardText text="DOCUMENT CONTROL NOTE." /></strong><DashboardText text="This is an editable HarborAI report template inspired by formal administrative report layouts. It is not an official Municipality of Aparri form or letterhead. Use official seals, logos, and signatories only when authorized. Verify all figures against system data before distribution." /></p>
        <p className="mt-3 border-t border-[#D8D3C4] pt-2 text-center text-xs text-muted-foreground"><DashboardText text="HARBORAI · ADMINISTRATIVE REPORT · System-generated; not an approval or official LGU-issued document." /></p>
        {preview.pagination && preview.pagination.last_page > 1 && <div className="mt-4 flex items-center justify-between gap-3 border-t border-border pt-3 text-sm text-muted-foreground"><span><DashboardText text="Page" />{' '}{preview.pagination.page}{' '}<DashboardText text="of" />{' '}{preview.pagination.last_page} · {preview.pagination.total_rows}{' '}<DashboardText text="rows" /></span><div className="flex gap-2"><Button size="sm" variant="outline" disabled={previewBusy || preview.pagination.page <= 1} onClick={() => savedReportId ? void openSavedReport(savedReportId, preview.pagination.page - 1) : void runPreview(preview.pagination.page - 1)}><ChevronLeft className="h-4 w-4" /><DashboardText text="Previous" /></Button><Button size="sm" variant="outline" disabled={previewBusy || preview.pagination.page >= preview.pagination.last_page} onClick={() => savedReportId ? void openSavedReport(savedReportId, preview.pagination.page + 1) : void runPreview(preview.pagination.page + 1)}><DashboardText text="Next" /><ChevronRight className="ml-1 h-4 w-4" /></Button></div></div>}
        {Object.keys(preview.definitions ?? {}).length > 0 && <details className="print:hidden mt-4 border-t border-border pt-3"><summary className="cursor-pointer text-sm font-bold text-[#0F9488]"><DashboardText text="Report definitions" /></summary><dl className="mt-2 space-y-2">{Object.entries(preview.definitions).map(([key, value]) => <div key={key}><dt className="text-xs font-bold text-foreground">{humanize(key)}</dt><dd className="text-xs leading-5 text-muted-foreground">{value}</dd></div>)}</dl></details>}
      </div>}
          </div>
    </DialogContent></Dialog>
  </div>;
}
