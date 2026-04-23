import React from 'react';
import ReactDOM from 'react-dom/client';
import { BrowserRouter, Routes, Route } from 'react-router-dom';

// CSS
import '../../src/styles/index.css';

// Main pages
import LandingPage from '../../src/app/pages/LandingPage';
import LoginPage from '../../src/app/pages/loginPage';
import RegisterPage from '../../src/app/pages/Registerpage';
import NotFound from '../../src/app/pages/NotFound';
import ProgramsPage from '../../src/app/pages/ProgramsPage';

// Dashboards (IMPORTANT)
import AdminDashboard from '../../src/app/pages/admin/AdminDashboard';
import BuyerDashboard from '../../src/app/pages/buyer/BuyerDashboard';
import ProducerDashboard from '../../src/app/pages/producer/ProducerDashboard';


function App() {
  return (
    <BrowserRouter>
      <Routes>
        <Route path="/" element={<LandingPage />} />
        <Route path="/login" element={<LoginPage />} />
        <Route path="/register" element={<RegisterPage />} />
        <Route path="/programs" element={<ProgramsPage />} />

        {/* DASHBOARDS */}
        <Route path="/admin" element={<AdminDashboard />} />
        <Route path="/buyer" element={<BuyerDashboard />} />
        <Route path="/producer" element={<ProducerDashboard />} />

        {/* 404 */}
        <Route path="*" element={<NotFound />} />
      </Routes>
    </BrowserRouter>
  );
}

ReactDOM.createRoot(document.getElementById('app')!).render(<App />);