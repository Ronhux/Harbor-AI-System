import React, { useState, useEffect } from "react";

export default function MyProfile() {
  const [isEditing, setIsEditing] = useState(false);
  const [producer, setProducer] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const fetchProfile = async () => {
      try {
        const authToken = localStorage.getItem('authToken');
        const headers: HeadersInit = {
          'Content-Type': 'application/json',
        };
        
        if (authToken) {
          headers['Authorization'] = `Bearer ${authToken}`;
        }

        const response = await fetch('/api/producer/dashboard', {
          headers,
        });
        
        if (!response.ok) {
          throw new Error('Failed to fetch producer profile');
        }
        const data = await response.json();
        setProducer(data.producer);
        setError(null);
      } catch (error) {
        console.error('Error fetching profile:', error);
        setError(error instanceof Error ? error.message : 'Failed to load profile');
      } finally {
        setLoading(false);
      }
    };
    fetchProfile();
  }, []);

  if (loading) {
    return <div className="p-6">Loading profile...</div>;
  }

  if (error) {
    return <div className="p-6 text-red-600">Error: {error}</div>;
  }

  if (!producer) {
    return <div className="p-6">Profile not found</div>;
  }

  return (
    <div className="p-6 space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold text-gray-900 flex items-center gap-3">
            My Profile
          </h1>
          <p className="text-gray-600 mt-1">Manage your account and enterprise information</p>
        </div>
        {!isEditing ? (
          <button 
            onClick={() => setIsEditing(true)}
            className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
          >
            Edit Profile
          </button>
        ) : (
          <div className="flex gap-2">
            <button 
              onClick={() => setIsEditing(false)}
              className="px-4 py-2 bg-gray-300 text-gray-800 rounded-lg hover:bg-gray-400"
            >
              Cancel
            </button>
            <button className="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
              Save Changes
            </button>
          </div>
        )}
      </div>
      <div className="mt-6 border rounded-lg p-4">
        <h2 className="text-xl font-bold text-gray-900 mb-1">{producer.name}</h2>
        <p className="text-gray-600 mb-3">Producer</p>
        <div className="space-y-3 pt-4 border-t">
          <div className="flex items-center gap-3 text-sm">
            <span className="font-semibold text-gray-700">Email:</span>
            <span className="text-gray-600">{producer.email}</span>
          </div>
          {producer.contact_number && (
            <div className="flex items-center gap-3 text-sm">
              <span className="font-semibold text-gray-700">Contact:</span>
              <span className="text-gray-600">{producer.contact_number}</span>
            </div>
          )}
          {producer.rsbsa_number && (
            <div className="flex items-center gap-3 text-sm">
              <span className="font-semibold text-gray-700">RSBSA Number:</span>
              <span className="text-gray-600">{producer.rsbsa_number}</span>
            </div>
          )}
          {producer.location && (
            <div className="flex items-center gap-3 text-sm">
              <span className="font-semibold text-gray-700">Location:</span>
              <span className="text-gray-600">{producer.location}</span>
            </div>
          )}
          {producer.primary_product_type && (
            <div className="flex items-center gap-3 text-sm">
              <span className="font-semibold text-gray-700">Primary Product:</span>
              <span className="text-gray-600">{producer.primary_product_type}</span>
            </div>
          )}
          {producer.verification_status && (
            <div className="flex items-center gap-3 text-sm">
              <span className="font-semibold text-gray-700">Verification Status:</span>
              <span className={`px-2 py-1 rounded-full text-xs font-medium ${
                producer.verification_status === 'Verified' 
                  ? 'bg-green-100 text-green-800' 
                  : 'bg-yellow-100 text-yellow-800'
              }`}>
                {producer.verification_status}
              </span>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
