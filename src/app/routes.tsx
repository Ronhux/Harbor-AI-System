import { createBrowserRouter } from "react-router-dom";
import LandingPage from "./pages/LandingPage";
import LoginPage from "./pages/LoginPage";
import RegisterPage from "./pages/RegisterPage";
import ProgramsPage from "./pages/ProgramsPage";
import AdminDashboard from "./pages/admin/AdminDashboard";
import ProducerDashboard from "./pages/producer/ProducerDashboard";
import BuyerDashboard from "./pages/buyer/BuyerDashboard";
import NotFound from "./pages/NotFound";
import { ProductBacklog } from "./components/ProductBacklog";

export const router = createBrowserRouter([
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
    path: "/programs",
    element: <ProgramsPage />,
  },
  {
    path: "/backlog",
    element: <ProductBacklog />,
  },
  {
    path: "/admin/*",
    element: <AdminDashboard />,
  },
  {
    path: "/producer/*",
    element: <ProducerDashboard />,
  },
  {
    path: "/buyer/*",
    element: <BuyerDashboard />,
  },
  {
    path: "*",
    element: <NotFound />,
  },
]);