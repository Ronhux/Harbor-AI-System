import { ClipboardList, Download, Calendar } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '../../../components/ui/card';
import { Button } from '../../../components/ui/button';
import { Badge } from '../../../components/ui/badge';

export default function Reports() {
  const reports = [
    { id: 1, name: 'Monthly Producer Activity Report', period: 'February 2026', type: 'Activity', generated: '2026-02-14', size: '2.4 MB' },
    { id: 2, name: 'Supply-Demand Analysis', period: 'Q1 2026', type: 'Analytics', generated: '2026-02-13', size: '1.8 MB' },
    { id: 3, name: 'Program Utilization Report', period: 'January 2026', type: 'Program', generated: '2026-02-01', size: '1.2 MB' },
    { id: 4, name: 'Procurement Summary', period: 'February 2026', type: 'Procurement', generated: '2026-02-12', size: '956 KB' },
  ];

  return (
    <div className="p-6 space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold text-gray-900">Reports</h1>
          <p className="text-gray-600 mt-1">Generate and download system reports</p>
        </div>
        <Button className="bg-purple-600 hover:bg-purple-700" onClick={() => alert('Report generation feature not yet implemented. This would open a form to select report type and parameters.')}>
          <Calendar className="w-4 h-4 mr-2" />
          Generate New Report
        </Button>
      </div>

      <div className="space-y-3">
        {reports.map((report) => (
          <Card key={report.id} className="border-2 hover:shadow-md transition">
            <CardContent className="p-4">
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-4 flex-1">
                  <div className="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                    <ClipboardList className="w-6 h-6 text-purple-600" />
                  </div>
                  <div className="flex-1">
                    <div className="flex items-center gap-2 mb-1">
                      <h3 className="font-bold text-gray-900">{report.name}</h3>
                      <Badge variant="secondary">{report.type}</Badge>
                    </div>
                    <div className="text-sm text-gray-600">
                      {report.period} • Generated {report.generated} • {report.size}
                    </div>
                  </div>
                </div>
                <Button variant="outline" onClick={() => alert('Download feature not yet implemented. This would download the report file.')}>
                  <Download className="w-4 h-4 mr-2" />
                  Download
                </Button>
              </div>
            </CardContent>
          </Card>
        ))}
      </div>
    </div>
  );
}
