import { lazy } from 'react';
import SessionRoleGate from "./components/SessionRoleGate";
import { createBrowserRouter } from "react-router-dom";
import LandingPage from "./pages/LandingPage";
const LoginPage = lazy(() => import('./pages/LoginPage'));
const FcaRegistrationPage = lazy(() => import('./pages/FcaRegistrationPage'));
const FcaReviewPage = lazy(() => import('./pages/FcaReviewPage'));
const RegisterPage = lazy(() => import('./pages/RegisterPage'));
const FaqPage = lazy(() => import('./pages/FaqPage'));
const PrivacyPolicyPage = lazy(() => import('./pages/PrivacyPolicyPage'));
const TermsAndConditionsPage = lazy(() => import('./pages/TermsAndConditionsPage'));
const AdminDashboard = lazy(() => import('./pages/admin/AdminDashboard'));
const ProducerDashboard = lazy(() => import('./pages/producer/ProducerDashboard'));
const BuyerDashboard = lazy(() => import('./pages/buyer/BuyerDashboard'));
const KadiwaDashboard = lazy(() => import('./pages/kadiwa/KadiwaDashboard'));
const NotFound = lazy(() => import('./pages/NotFound'));
import { ProductBacklog } from "./components/ProductBacklog";
const SuperAdminDashboard = lazy(() => import('./pages/admin/SuperAdminDashboard'));
const PublicInfoPage = lazy(() => import('./pages/PublicInfoPage'));

export const router = createBrowserRouter([
  {path:"/register/fca",element:<FcaRegistrationPage/>},
  {
    path: "/",
    element: <LandingPage />,
  },
  {
    path: "/login",
    element: <LoginPage />,
  },
  {
    path: "/register",
    element: <RegisterPage />,
  },
  {
    path: "/faq",
    element: <FaqPage />,
  },
  {
    path: "/privacy",
    element: <PrivacyPolicyPage />,
  },
  {
    path: "/terms-and-conditions",
    element: <TermsAndConditionsPage />,
  },
  // Public alias for the same Terms and Conditions page.
  { path: "/terms", element: <TermsAndConditionsPage /> },
  { path: "/help", element: <PublicInfoPage /> },
  { path: "/contact", element: <PublicInfoPage /> },
  { path: "/data-protection", element: <PublicInfoPage /> },
  {
    path: "/backlog",
    element: <ProductBacklog />,
  },
  { path: "/admin/fca-review", element: <FcaReviewPage /> },
  {
    path: "/admin/*",
    element: <AdminDashboard />,
  },
  {
    path: "/superadmin/*",
    element: <SuperAdminDashboard />,
  },
  {
    path: "/producer/*",
    element: <ProducerDashboard />,
  },
  {
    path: "/kadiwa/*",
    element: <SessionRoleGate role="kadiwa"><KadiwaDashboard /></SessionRoleGate>,
  },
  {
    path: "/buyer/*",
    element: <SessionRoleGate role="buyer"><BuyerDashboard /></SessionRoleGate>,
  },
  {
    path: "*",
    element: <NotFound />,
  },
]);
