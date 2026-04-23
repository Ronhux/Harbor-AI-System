import { useState, useEffect } from 'react';
import { Upload, Search, Edit, RefreshCw, FileText } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '../../../components/ui/card';
import { Button } from '../../../components/ui/button';
import { Input } from '../../../components/ui/input';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '../../../components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '../../../components/ui/table';

interface RegistryEntry {
  id: number;
  rsbsa_number: string;
  name: string;
  barangay: string;
  province: string;
  farm_type: string;
  farm_size: string;
  contact_number: string;
  email: string;
}

export default function RegistryManagement() {
  const [registries, setRegistries] = useState<RegistryEntry[]>([]);
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedEntry, setSelectedEntry] = useState<RegistryEntry | null>(null);
  const [isEditDialogOpen, setIsEditDialogOpen] = useState(false);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    fetchRegistries();
  }, []);

  const fetchRegistries = async () => {
    try {
      const response = await fetch('/api/admin/registry');
      const data = await response.json();
      setRegistries(data.data || []);
    } catch (error) {
      console.error('Error fetching registries:', error);
    }
  };

  const handleImport = async (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    if (!file) return;

    setLoading(true);
    const formData = new FormData();
    formData.append('file', file);

    try {
      const response = await fetch('/api/admin/registry/import', {
        method: 'POST',
        body: formData,
      });
      const result = await response.json();
      alert(result.message);
      fetchRegistries();
    } catch (error) {
      console.error('Error importing registry:', error);
      alert('Import failed');
    } finally {
      setLoading(false);
    }
  };

  const handleSearch = async () => {
    try {
      const response = await fetch(`/api/admin/registry/search?name=${searchTerm}`);
      const data = await response.json();
      setRegistries(data.data || []);
    } catch (error) {
      console.error('Error searching registries:', error);
    }
  };

  const handleEdit = async () => {
    if (!selectedEntry) return;

    try {
      const response = await fetch(`/api/admin/registry/${selectedEntry.id}`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(selectedEntry),
      });
      const result = await response.json();
      alert(result.message);
      setIsEditDialogOpen(false);
      fetchRegistries();
    } catch (error) {
      console.error('Error updating registry:', error);
      alert('Update failed');
    }
  };

  const handleSync = async () => {
    setLoading(true);
    try {
      const response = await fetch('/api/admin/registry/sync', {
        method: 'POST',
      });
      const result = await response.json();
      alert(result.message);
    } catch (error) {
      console.error('Error syncing registry:', error);
      alert('Sync failed');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="p-6 space-y-6">
      <div>
        <h1 className="text-3xl font-bold text-gray-900">Registry Management</h1>
        <p className="text-gray-600 mt-1">Manage the registered producers database</p>
      </div>

      <div className="flex gap-4 mb-6">
        <div className="flex-1">
          <div className="flex gap-2">
            <Input
              placeholder="Search by name or barangay..."
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              className="flex-1"
            />
            <Button onClick={handleSearch}>
              <Search className="w-4 h-4" />
            </Button>
          </div>
        </div>
        <div className="flex gap-2">
          <Button variant="outline" disabled={loading}>
            <label className="cursor-pointer flex items-center gap-2">
              <Upload className="w-4 h-4" />
              Import CSV
              <input
                type="file"
                accept=".csv"
                onChange={handleImport}
                className="hidden"
                disabled={loading}
              />
            </label>
          </Button>
          <Button variant="outline" onClick={handleSync} disabled={loading}>
            <RefreshCw className={`w-4 h-4 ${loading ? 'animate-spin' : ''}`} />
            Sync Data
          </Button>
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <FileText className="w-5 h-5" />
            Registered Producers ({registries.length})
          </CardTitle>
        </CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>RSBSA Number</TableHead>
                <TableHead>Name</TableHead>
                <TableHead>Barangay</TableHead>
                <TableHead>Farm Type</TableHead>
                <TableHead>Contact</TableHead>
                <TableHead>Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {registries.map((entry) => (
                <TableRow key={entry.id}>
                  <TableCell>{entry.rsbsa_number}</TableCell>
                  <TableCell>{entry.name}</TableCell>
                  <TableCell>{entry.barangay}</TableCell>
                  <TableCell>{entry.farm_type}</TableCell>
                  <TableCell>{entry.contact_number}</TableCell>
                  <TableCell>
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => {
                        setSelectedEntry(entry);
                        setIsEditDialogOpen(true);
                      }}
                    >
                      <Edit className="w-4 h-4" />
                    </Button>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>

      <Dialog open={isEditDialogOpen} onOpenChange={setIsEditDialogOpen}>
        <DialogContent className="max-w-2xl">
          <DialogHeader>
            <DialogTitle>Edit Registry Entry</DialogTitle>
          </DialogHeader>
          {selectedEntry && (
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium mb-1">RSBSA Number</label>
                <Input
                  value={selectedEntry.rsbsa_number}
                  onChange={(e) => setSelectedEntry({...selectedEntry, rsbsa_number: e.target.value})}
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-1">Name</label>
                <Input
                  value={selectedEntry.name}
                  onChange={(e) => setSelectedEntry({...selectedEntry, name: e.target.value})}
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-1">Barangay</label>
                <Input
                  value={selectedEntry.barangay}
                  onChange={(e) => setSelectedEntry({...selectedEntry, barangay: e.target.value})}
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-1">Province</label>
                <Input
                  value={selectedEntry.province}
                  onChange={(e) => setSelectedEntry({...selectedEntry, province: e.target.value})}
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-1">Farm Type</label>
                <Input
                  value={selectedEntry.farm_type}
                  onChange={(e) => setSelectedEntry({...selectedEntry, farm_type: e.target.value})}
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-1">Farm Size</label>
                <Input
                  value={selectedEntry.farm_size}
                  onChange={(e) => setSelectedEntry({...selectedEntry, farm_size: e.target.value})}
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-1">Contact Number</label>
                <Input
                  value={selectedEntry.contact_number}
                  onChange={(e) => setSelectedEntry({...selectedEntry, contact_number: e.target.value})}
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-1">Email</label>
                <Input
                  value={selectedEntry.email}
                  onChange={(e) => setSelectedEntry({...selectedEntry, email: e.target.value})}
                />
              </div>
              <div className="col-span-2 flex justify-end gap-2 mt-4">
                <Button variant="outline" onClick={() => setIsEditDialogOpen(false)}>
                  Cancel
                </Button>
                <Button onClick={handleEdit}>
                  Save Changes
                </Button>
              </div>
            </div>
          )}
        </DialogContent>
      </Dialog>
    </div>
  );
}