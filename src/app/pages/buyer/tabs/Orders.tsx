import { useEffect, useMemo, useState } from 'react';
import {
  CheckCircle2,
  Clock,
  Truck,
  Package,
  Search,
  SlidersHorizontal,
  ChevronDown,
  ArrowRight,
} from 'lucide-react';

import { Card, CardContent } from '../../../components/ui/card';
import { Badge } from '../../../components/ui/badge';
import { Button } from '../../../components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '../../../components/ui/dialog';

interface Producer {
  producer_id: number;
  first_name?: string;
  last_name?: string;
  name?: string;
}

interface ProductListing {
  listing_id: number;
  product_name: string;
  unit_of_measure?: string;
  unit?: string;
  producer?: Producer;
}

interface OrderDetail {
  order_detail_id: number;
  listing_id: number;
  quantity: number | string;
  unit_price: number | string;
  subtotal: number | string;
  listing?: ProductListing;
}

interface Order {
  order_id: number;
  buyer_id: number;
  order_date: string;
  total_amount: number | string;
  shipping_address?: string;
  fulfillment_status: string;
  payment_status: string;
  details: OrderDetail[];
}

type OrderTab = 'ongoing' | 'completed';

const getLaravelBaseUrl = (): string => {
  const configured = String(import.meta.env.VITE_API_URL || '').trim();

  if (configured) {
    return configured.replace(/\/+$/, '').replace(/\/api$/, '');
  }

  return 'http://127.0.0.1:8000';
};

const getApiUrl = (path: string): string => {
  const cleanPath = path.replace(/^\/+/, '');
  return `${getLaravelBaseUrl()}/${cleanPath}`;
};

const normalizeStatus = (status?: string): string => {
  return String(status || '')
    .trim()
    .toLowerCase()
    .replace(/-/g, ' ')
    .replace(/_/g, ' ');
};

const isCompletedOrder = (order: Order): boolean => {
  const status = normalizeStatus(order.fulfillment_status);

  return [
    'delivered',
    'completed',
    'cancelled',
    'canceled',
    'rejected',
  ].includes(status);
};

const getStatusConfig = (status: string) => {
  const normalizedStatus = normalizeStatus(status);

  if (normalizedStatus === 'delivered' || normalizedStatus === 'completed') {
    return {
      icon: CheckCircle2,
      iconWrap: 'bg-emerald-50 text-emerald-700',
      badge: 'bg-emerald-50 text-emerald-700 border-emerald-200',
      label: status || 'Completed',
    };
  }

  if (normalizedStatus === 'in transit') {
    return {
      icon: Truck,
      iconWrap: 'bg-violet-50 text-violet-700',
      badge: 'bg-violet-50 text-violet-700 border-violet-200',
      label: status || 'In Transit',
    };
  }

  if (
    normalizedStatus === 'confirmed' ||
    normalizedStatus === 'pending' ||
    normalizedStatus === 'processing'
  ) {
    return {
      icon: Clock,
      iconWrap: 'bg-blue-50 text-blue-700',
      badge: 'bg-blue-50 text-blue-700 border-blue-200',
      label: status || 'Processing',
    };
  }

  if (
    normalizedStatus === 'cancelled' ||
    normalizedStatus === 'canceled' ||
    normalizedStatus === 'rejected'
  ) {
    return {
      icon: Clock,
      iconWrap: 'bg-red-50 text-red-700',
      badge: 'bg-red-50 text-red-700 border-red-200',
      label: status || 'Cancelled',
    };
  }

  return {
    icon: Clock,
    iconWrap: 'bg-slate-50 text-slate-700',
    badge: 'bg-slate-50 text-slate-700 border-slate-200',
    label: status || 'Pending',
  };
};

const getProducerName = (producer?: Producer) => {
  if (!producer) {
    return 'Hindi kilalang Producer';
  }

  if (producer.name) {
    return producer.name;
  }

  return (
    `${producer.first_name ?? ''} ${producer.last_name ?? ''}`.trim() ||
    'Hindi kilalang Producer'
  );
};

const formatAmount = (amount: number | string) => {
  const numericAmount = Number(amount);

  if (!Number.isFinite(numericAmount)) {
    return '0.00';
  }

  return numericAmount.toLocaleString('en-PH', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
};

const formatOrderDate = (date?: string) => {
  if (!date) {
    return 'N/A';
  }

  const parsed = new Date(date);

  if (Number.isNaN(parsed.getTime())) {
    return date;
  }

  return parsed.toLocaleDateString('en-PH', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
};

export default function Orders() {
  const [orders, setOrders] = useState<Order[]>([]);
  const [selectedOrder, setSelectedOrder] = useState<Order | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const [activeTab, setActiveTab] = useState<OrderTab>('ongoing');
  const [searchTerm, setSearchTerm] = useState('');
  const [statusFilter, setStatusFilter] = useState('all');
  const [showStatusMenu, setShowStatusMenu] = useState(false);

  useEffect(() => {
    fetchOrders();

    const handleOrderPlaced = () => {
      fetchOrders();
    };

    window.addEventListener('orderPlaced', handleOrderPlaced);

    return () => {
      window.removeEventListener('orderPlaced', handleOrderPlaced);
    };
  }, []);

  const fetchOrders = async () => {
    try {
      setLoading(true);
      setError('');

      const token = localStorage.getItem('authToken');

      const response = await fetch(getApiUrl('/api/buyer/orders'), {
        method: 'GET',
        headers: {
          Accept: 'application/json',
          ...(token
            ? {
                Authorization: `Bearer ${token}`,
              }
            : {}),
        },
        credentials: 'include',
      });

      if (!response.ok) {
        throw new Error(`Failed to fetch orders (${response.status})`);
      }

      const data = await response.json();

      const nextOrders = Array.isArray(data)
        ? data
        : Array.isArray(data?.data)
          ? data.data
          : Array.isArray(data?.orders)
            ? data.orders
            : [];

      setOrders(nextOrders);
    } catch (err) {
      console.error('Error loading orders:', err);
      setError('Unable to load your orders.');
      setOrders([]);
    } finally {
      setLoading(false);
    }
  };

  const ongoingCount = useMemo(
    () => orders.filter((order) => !isCompletedOrder(order)).length,
    [orders],
  );

  const completedCount = useMemo(
    () => orders.filter((order) => isCompletedOrder(order)).length,
    [orders],
  );

  const statusOptions = useMemo(() => {
    const values = orders
      .map((order) => order.fulfillment_status)
      .filter(Boolean);

    return Array.from(new Set(values));
  }, [orders]);

  const filteredOrders = useMemo(() => {
    const normalizedSearch = searchTerm.trim().toLowerCase();

    return orders.filter((order) => {
      const matchesTab =
        activeTab === 'completed'
          ? isCompletedOrder(order)
          : !isCompletedOrder(order);

      const matchesSearch =
        !normalizedSearch ||
        `ORD-${order.order_id}`.toLowerCase().includes(normalizedSearch) ||
        String(order.order_id).includes(normalizedSearch) ||
        order.details?.some((detail) =>
          String(detail.listing?.product_name || '')
            .toLowerCase()
            .includes(normalizedSearch),
        );

      const matchesStatus =
        statusFilter === 'all' ||
        normalizeStatus(order.fulfillment_status) ===
          normalizeStatus(statusFilter);

      return matchesTab && matchesSearch && matchesStatus;
    });
  }, [orders, activeTab, searchTerm, statusFilter]);

  const handleTabChange = (tab: OrderTab) => {
    setActiveTab(tab);
    setSearchTerm('');
    setStatusFilter('all');
    setShowStatusMenu(false);
  };

  const activeEmptyMessage =
    activeTab === 'ongoing'
      ? 'Start an order from your market cart.'
      : 'Your completed orders will appear here.';

  if (loading) {
    return (
      <div className="min-h-full bg-[#F8F8F5] px-5 py-8 md:px-8 lg:px-12">
        <div className="mx-auto max-w-[1440px]">
          <div className="mb-7">
            <div className="mb-3 h-7 w-28 animate-pulse rounded-full bg-[#E8F3E8]" />
            <div className="h-11 w-64 animate-pulse rounded-lg bg-[#E8E7E2]" />
            <div className="mt-3 h-5 w-96 max-w-full animate-pulse rounded bg-[#E8E7E2]" />
          </div>

          <div className="h-px w-full bg-[#E6E2D9]" />

          <div className="mt-6 h-12 w-full animate-pulse rounded-xl bg-white ring-1 ring-[#E8E4DB]" />
          <div className="mt-5 h-10 w-72 animate-pulse rounded bg-[#E8E4DB]" />
          <div className="mt-8 flex justify-center">
            <div className="h-36 w-96 max-w-full animate-pulse rounded-2xl bg-white" />
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-full bg-[#F8F8F5] px-5 py-8 md:px-8 lg:px-12">
      <div className="mx-auto max-w-[1440px]">
        {/* =========================================================
            PAGE HEADER
        ========================================================== */}
        <header className="mb-6">
          <div className="inline-flex items-center rounded-full bg-[#EAF5EA] px-3.5 py-1.5 text-xs font-bold tracking-[0.02em] text-[#2F7D3C]">
            HARBORAI ORDERS
          </div>

          <h1 className="mt-3 text-4xl font-black tracking-[-0.04em] text-[#151B2A] md:text-5xl">
            Aking Orders
          </h1>

          <p className="mt-2 text-base text-[#766F64] md:text-lg">
            Subaybayan ang iyong mga order, delivery, at order details.
          </p>
        </header>

        <div className="h-px w-full bg-[#E5E1D8]" />

        {/* =========================================================
            SEARCH + STATUS FILTER
        ========================================================== */}
        <section className="mt-6">
          <div className="grid grid-cols-1 gap-3 md:grid-cols-[minmax(0,1fr)_300px]">
            <div className="relative">
              <Search
                className="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-[#9B948A]"
                strokeWidth={1.8}
              />

              <input
                type="text"
                value={searchTerm}
                onChange={(event) => setSearchTerm(event.target.value)}
                placeholder="Search by order ID"
                className="h-12 w-full rounded-xl border border-[#E6E0D6] bg-white pl-12 pr-4 text-[15px] text-[#29251F] outline-none transition placeholder:text-[#A29A90] focus:border-[#3A7D44] focus:ring-2 focus:ring-[#3A7D44]/10"
              />
            </div>

            <div className="relative">
              <button
                type="button"
                onClick={() => setShowStatusMenu((current) => !current)}
                className="flex h-12 w-full items-center justify-between rounded-xl border border-[#E6E0D6] bg-white px-4 text-left text-[15px] font-semibold text-[#3B362F] transition hover:border-[#BDB6AA]"
              >
                <span className="flex items-center gap-3">
                  <SlidersHorizontal
                    className="h-4 w-4 text-[#81796E]"
                    strokeWidth={1.8}
                  />
                  <span>
                    {statusFilter === 'all'
                      ? 'All statuses'
                      : statusFilter}
                  </span>
                </span>

                <ChevronDown
                  className={`h-4 w-4 text-[#9A9389] transition ${
                    showStatusMenu ? 'rotate-180' : ''
                  }`}
                />
              </button>

              {showStatusMenu && (
                <div className="absolute right-0 top-[calc(100%+8px)] z-30 w-full overflow-hidden rounded-xl border border-[#E5E0D7] bg-white p-1.5 shadow-[0_14px_40px_rgba(45,40,30,0.12)]">
                  <button
                    type="button"
                    onClick={() => {
                      setStatusFilter('all');
                      setShowStatusMenu(false);
                    }}
                    className={`w-full rounded-lg px-3 py-2.5 text-left text-sm transition ${
                      statusFilter === 'all'
                        ? 'bg-[#EFF7EF] font-semibold text-[#2F7D3C]'
                        : 'text-[#514B43] hover:bg-[#F7F5F1]'
                    }`}
                  >
                    All statuses
                  </button>

                  {statusOptions.map((status) => {
                    const selected =
                      normalizeStatus(statusFilter) === normalizeStatus(status);

                    return (
                      <button
                        key={status}
                        type="button"
                        onClick={() => {
                          setStatusFilter(status);
                          setShowStatusMenu(false);
                        }}
                        className={`w-full rounded-lg px-3 py-2.5 text-left text-sm transition ${
                          selected
                            ? 'bg-[#EFF7EF] font-semibold text-[#2F7D3C]'
                            : 'text-[#514B43] hover:bg-[#F7F5F1]'
                        }`}
                      >
                        {status}
                      </button>
                    );
                  })}
                </div>
              )}
            </div>
          </div>
        </section>

        {/* =========================================================
            TABS
        ========================================================== */}
        <nav className="mt-5 border-b border-[#E5E1D8]">
          <div className="flex items-end gap-7">
            <button
              type="button"
              onClick={() => handleTabChange('ongoing')}
              className={`relative flex items-center gap-2 px-1 pb-3 pt-1 text-[15px] font-bold transition ${
                activeTab === 'ongoing'
                  ? 'text-[#2F7D3C]'
                  : 'text-[#9A9287] hover:text-[#5F594F]'
              }`}
            >
              Ongoing
              <span
                className={`inline-flex min-w-5 items-center justify-center rounded-full px-1.5 py-0.5 text-xs font-bold ${
                  activeTab === 'ongoing'
                    ? 'bg-[#DDEEDC] text-[#2F7D3C]'
                    : 'bg-[#F0EEE9] text-[#A39B90]'
                }`}
              >
                {ongoingCount}
              </span>

              {activeTab === 'ongoing' && (
                <span className="absolute bottom-[-1px] left-0 right-0 h-[3px] rounded-full bg-[#2F7D3C]" />
              )}
            </button>

            <button
              type="button"
              onClick={() => handleTabChange('completed')}
              className={`relative flex items-center gap-2 px-1 pb-3 pt-1 text-[15px] font-bold transition ${
                activeTab === 'completed'
                  ? 'text-[#2F7D3C]'
                  : 'text-[#9A9287] hover:text-[#5F594F]'
              }`}
            >
              Completed
              <span
                className={`inline-flex min-w-5 items-center justify-center rounded-full px-1.5 py-0.5 text-xs font-bold ${
                  activeTab === 'completed'
                    ? 'bg-[#DDEEDC] text-[#2F7D3C]'
                    : 'bg-[#F0EEE9] text-[#A39B90]'
                }`}
              >
                {completedCount}
              </span>

              {activeTab === 'completed' && (
                <span className="absolute bottom-[-1px] left-0 right-0 h-[3px] rounded-full bg-[#2F7D3C]" />
              )}
            </button>
          </div>
        </nav>

        {/* =========================================================
            ERROR
        ========================================================== */}
        {error && (
          <Card className="mt-6 rounded-2xl border border-red-200 bg-white shadow-none">
            <CardContent className="p-4">
              <p className="text-sm font-medium text-red-600">{error}</p>
            </CardContent>
          </Card>
        )}

        {/* =========================================================
            ORDER RESULTS
        ========================================================== */}
        {!error && filteredOrders.length > 0 && (
          <div className="mt-7 space-y-4">
            {filteredOrders.map((order) => {
              const config = getStatusConfig(order.fulfillment_status);
              const StatusIcon = config.icon;

              return (
                <Card
                  key={order.order_id}
                  className="overflow-hidden rounded-2xl border border-[#E7E2D9] bg-white shadow-[0_4px_18px_rgba(47,42,32,0.04)]"
                >
                  <CardContent className="p-5 md:p-6">
                    <div className="flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
                      <div className="flex min-w-0 items-start gap-4">
                        <div
                          className={`flex h-12 w-12 shrink-0 items-center justify-center rounded-xl ${config.iconWrap}`}
                        >
                          <StatusIcon className="h-6 w-6" strokeWidth={2} />
                        </div>

                        <div className="min-w-0 flex-1">
                          <div className="flex flex-wrap items-center gap-2">
                            <h3 className="text-base font-extrabold text-[#29251F]">
                              ORD-{order.order_id}
                            </h3>

                            <span
                              className={`rounded-full border px-2.5 py-1 text-xs font-bold ${config.badge}`}
                            >
                              {config.label}
                            </span>
                          </div>

                          <div className="mt-2 space-y-1.5 text-sm text-[#756E64]">
                            {order.details?.map((detail) => (
                              <div
                                key={detail.order_detail_id}
                                className="leading-5"
                              >
                                <span className="font-semibold text-[#514B43]">
                                  {detail.listing?.product_name ?? 'Product'}
                                </span>
                                {' • '}
                                {detail.quantity}{' '}
                                {detail.listing?.unit_of_measure ??
                                  detail.listing?.unit ??
                                  ''}
                                {' • '}
                                {getProducerName(detail.listing?.producer)}
                              </div>
                            ))}
                          </div>

                          <p className="mt-2 text-xs text-[#9A9287]">
                            Ordered {formatOrderDate(order.order_date)}
                          </p>
                        </div>
                      </div>

                      <div className="flex shrink-0 items-center justify-between gap-5 md:flex-col md:items-end">
                        <div className="text-left md:text-right">
                          <div className="text-xl font-black tracking-tight text-[#29251F]">
                            ₱{formatAmount(order.total_amount)}
                          </div>

                          <div className="mt-1 text-xs text-[#8D857A]">
                            {order.payment_status}
                          </div>
                        </div>

                        <Button
                          size="sm"
                          variant="outline"
                          className="rounded-lg border-[#DCD6CB] bg-white font-semibold text-[#3E3932] hover:bg-[#F7F5F0]"
                          onClick={() => setSelectedOrder(order)}
                        >
                          Tingnan ang Detalye
                        </Button>
                      </div>
                    </div>
                  </CardContent>
                </Card>
              );
            })}
          </div>
        )}

        {/* =========================================================
            EMPTY STATE
        ========================================================== */}
        {!error && filteredOrders.length === 0 && (
          <section className="flex min-h-[430px] flex-col items-center justify-start px-4 pt-20 text-center md:pt-24">
            <div className="flex h-16 w-16 items-center justify-center text-[#2F7D3C]">
              <Package
                className="h-14 w-14"
                strokeWidth={1.8}
              />
            </div>

            <h2 className="mt-5 text-2xl font-black uppercase tracking-[-0.02em] text-[#5B554D]">
              {searchTerm || statusFilter !== 'all'
                ? 'No matching orders'
                : activeTab === 'ongoing'
                  ? 'No ongoing orders'
                  : 'No completed orders'}
            </h2>

            <p className="mt-2 max-w-md text-base text-[#9B9184]">
              {searchTerm || statusFilter !== 'all'
                ? 'Try changing your search or status filter.'
                : activeEmptyMessage}
            </p>

            {activeTab === 'ongoing' &&
              !searchTerm &&
              statusFilter === 'all' && (
                <button
                  type="button"
                  onClick={() =>
                    window.dispatchEvent(
                      new CustomEvent('navigateToBuyerTab', {
                        detail: { tab: 'browse-producers' },
                      }),
                    )
                  }
                  className="group mt-2 inline-flex items-center gap-1 font-bold text-[#2F7D3C] underline decoration-2 underline-offset-4 transition hover:text-[#256431]"
                >
                  market cart
                  <ArrowRight
                    className="h-4 w-4 transition-transform group-hover:translate-x-0.5"
                  />
                </button>
              )}
          </section>
        )}

        {/* =========================================================
            ORDER DETAILS DIALOG
        ========================================================== */}
        <Dialog
          open={!!selectedOrder}
          onOpenChange={() => setSelectedOrder(null)}
        >
          <DialogContent className="max-h-[90vh] max-w-2xl overflow-y-auto rounded-2xl">
            <DialogHeader>
              <DialogTitle className="text-xl font-black text-[#151B2A]">
                Order Details -{' '}
                {selectedOrder ? `ORD-${selectedOrder.order_id}` : ''}
              </DialogTitle>

              <DialogDescription>
                Kumpletong impormasyon tungkol sa order na ito
              </DialogDescription>
            </DialogHeader>

            {selectedOrder && (
              <div className="space-y-6">
                {/* Order Information + Payment */}
                <div className="grid gap-6 md:grid-cols-2">
                  <div className="rounded-xl bg-[#F8F7F3] p-4">
                    <h4 className="mb-3 font-bold text-[#29251F]">
                      Impormasyon ng Order
                    </h4>

                    <div className="space-y-3 text-sm">
                      <div className="flex justify-between gap-4">
                        <span className="text-[#756E64]">Order ID:</span>
                        <span className="font-semibold text-[#29251F]">
                          ORD-{selectedOrder.order_id}
                        </span>
                      </div>

                      <div className="flex justify-between gap-4">
                        <span className="text-[#756E64]">Petsa ng Order:</span>
                        <span className="font-semibold text-right text-[#29251F]">
                          {formatOrderDate(selectedOrder.order_date)}
                        </span>
                      </div>

                      <div className="flex justify-between gap-4">
                        <span className="text-[#756E64]">
                          Shipping Address:
                        </span>
                        <span className="max-w-[250px] text-right font-semibold text-[#29251F]">
                          {selectedOrder.shipping_address ?? 'N/A'}
                        </span>
                      </div>
                    </div>
                  </div>

                  <div className="rounded-xl bg-[#F8F7F3] p-4">
                    <h4 className="mb-3 font-bold text-[#29251F]">
                      Status at Payment
                    </h4>

                    <div className="space-y-3 text-sm">
                      <div className="flex items-center justify-between gap-4">
                        <span className="text-[#756E64]">Status ng Order:</span>

                        <span
                          className={`rounded-full border px-2.5 py-1 text-xs font-bold ${
                            getStatusConfig(selectedOrder.fulfillment_status)
                              .badge
                          }`}
                        >
                          {selectedOrder.fulfillment_status}
                        </span>
                      </div>

                      <div className="flex justify-between gap-4">
                        <span className="text-[#756E64]">Payment:</span>
                        <span className="font-semibold text-[#29251F]">
                          {selectedOrder.payment_status}
                        </span>
                      </div>

                      <div className="flex justify-between gap-4 border-t border-[#E2DED6] pt-3">
                        <span className="text-[#756E64]">
                          Kabuuang Halaga:
                        </span>

                        <span className="text-lg font-black text-[#2F7D3C]">
                          ₱{formatAmount(selectedOrder.total_amount)}
                        </span>
                      </div>
                    </div>
                  </div>
                </div>

                {/* Ordered Products */}
                <div className="border-t border-[#E6E1D8] pt-5">
                  <h4 className="mb-3 font-bold text-[#29251F]">
                    Mga Na-order na Produkto
                  </h4>

                  <div className="space-y-3">
                    {selectedOrder.details?.map((detail) => (
                      <div
                        key={detail.order_detail_id}
                        className="rounded-xl border border-[#E6E1D8] bg-white p-4"
                      >
                        <div className="flex flex-col justify-between gap-3 sm:flex-row">
                          <div>
                            <p className="font-semibold text-[#29251F]">
                              {detail.listing?.product_name ?? 'Product'}
                            </p>

                            <p className="mt-1 text-sm text-[#756E64]">
                              Producer:{' '}
                              {getProducerName(detail.listing?.producer)}
                            </p>

                            <p className="mt-1 text-sm text-[#756E64]">
                              Quantity: {detail.quantity}{' '}
                              {detail.listing?.unit_of_measure ??
                                detail.listing?.unit ??
                                ''}
                            </p>
                          </div>

                          <div className="text-left sm:text-right">
                            <p className="font-semibold text-[#29251F]">
                              ₱{formatAmount(detail.subtotal)}
                            </p>

                            <p className="mt-1 text-sm text-[#8D857A]">
                              ₱{formatAmount(detail.unit_price)} per unit
                            </p>
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>

                {/* Timeline */}
                <div className="border-t border-[#E6E1D8] pt-5">
                  <h4 className="mb-4 font-bold text-[#29251F]">
                    Order Timeline
                  </h4>

                  <div className="space-y-4">
                    <div className="flex items-center gap-3">
                      <div className="h-3 w-3 rounded-full bg-[#2F7D3C]" />
                      <span className="text-sm text-[#514B43]">
                        Order placed on {formatOrderDate(selectedOrder.order_date)}
                      </span>
                    </div>

                    {normalizeStatus(selectedOrder.fulfillment_status) !==
                      'confirmed' && (
                      <div className="flex items-center gap-3">
                        <div className="h-3 w-3 rounded-full bg-blue-500" />
                        <span className="text-sm text-[#514B43]">
                          Order accepted and processing
                        </span>
                      </div>
                    )}

                    {['in transit', 'delivered'].includes(
                      normalizeStatus(selectedOrder.fulfillment_status),
                    ) && (
                      <div className="flex items-center gap-3">
                        <div className="h-3 w-3 rounded-full bg-violet-500" />
                        <span className="text-sm text-[#514B43]">
                          Order shipped
                        </span>
                      </div>
                    )}

                    {normalizeStatus(selectedOrder.fulfillment_status) ===
                      'delivered' && (
                      <div className="flex items-center gap-3">
                        <div className="h-3 w-3 rounded-full bg-[#2F7D3C]" />
                        <span className="text-sm text-[#514B43]">
                          Order delivered successfully
                        </span>
                      </div>
                    )}
                  </div>
                </div>
              </div>
            )}
          </DialogContent>
        </Dialog>
      </div>
    </div>
  );
}
