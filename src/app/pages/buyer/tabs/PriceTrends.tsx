import {
  ArrowDown,
  ArrowUp,
  BarChart3,
  BookOpen,
  CalendarDays,
  ChevronDown,
  Fish,
  Info,
  Leaf,
  Lightbulb,
  LineChart as LineChartIcon,
  Sprout,
  TrendingUp,
  Users,
  Wheat,
} from 'lucide-react';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '../../../components/ui/card';
import {
  LineChart,
  Line,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
} from 'recharts';

type ProductKey = 'rice' | 'corn' | 'fish' | 'vegetables';

type ProductConfig = {
  key: ProductKey;
  name: string;
  current: number;
  previous: number;
  color: string;
  bg: string;
  border: string;
  icon: React.ElementType;
};

const priceData = [
  { month: 'Jul', rice: 115, corn: 38, fish: 162, vegetables: 54 },
  { month: 'Aug', rice: 118, corn: 40, fish: 168, vegetables: 56 },
  { month: 'Sep', rice: 121, corn: 42, fish: 175, vegetables: 59 },
  { month: 'Oct', rice: 124, corn: 44, fish: 181, vegetables: 62 },
  { month: 'Nov', rice: 128, corn: 47, fish: 189, vegetables: 66 },
];

const products: ProductConfig[] = [
  {
    key: 'rice',
    name: 'Rice',
    current: 128,
    previous: 115,
    color: '#16A05A',
    bg: 'bg-[#EAF8EF]',
    border: 'border-[#BFE7CE]',
    icon: Wheat,
  },
  {
    key: 'corn',
    name: 'Corn',
    current: 47,
    previous: 38,
    color: '#F59E0B',
    bg: 'bg-[#FFF6E5]',
    border: 'border-[#F6D69A]',
    icon: Sprout,
  },
  {
    key: 'fish',
    name: 'Fish',
    current: 189,
    previous: 163,
    color: '#1298D4',
    bg: 'bg-[#EAF6FD]',
    border: 'border-[#B9E1F5]',
    icon: Fish,
  },
  {
    key: 'vegetables',
    name: 'Vegetables',
    current: 66,
    previous: 54,
    color: '#0F9488',
    bg: 'bg-[#EDF8F3]',
    border: 'border-[#C4E5D4]',
    icon: Leaf,
  },
];

const percentChange = (current: number, previous: number) =>
  ((current - previous) / previous) * 100;

const formatPrice = (value: number) => `₱${value}/kg`;

function ProductIcon({ product, size = 'md' }: { product: ProductConfig; size?: 'sm' | 'md' }) {
  const Icon = product.icon;

  return (
    <div
      className={`flex shrink-0 items-center justify-center rounded-2xl ${product.bg} ${
        size === 'sm' ? 'h-11 w-11' : 'h-16 w-16'
      }`}
      style={{ color: product.color }}
    >
      <Icon className={size === 'sm' ? 'h-6 w-6' : 'h-8 w-8'} strokeWidth={2} />
    </div>
  );
}

function ChangeValue({ value }: { value: number }) {
  const isPositive = value >= 0;

  return (
    <span
      className={`inline-flex items-center gap-1 font-bold ${
        isPositive ? 'text-[#16A05A]' : 'text-red-500'
      }`}
    >
      {isPositive ? (
        <ArrowUp className="h-5 w-5" strokeWidth={3} />
      ) : (
        <ArrowDown className="h-5 w-5" strokeWidth={3} />
      )}
      {Math.abs(value).toFixed(1)}%
    </span>
  );
}

function SelectControl({
  icon: Icon,
  children,
  className = '',
}: {
  icon: React.ElementType;
  children: React.ReactNode;
  className?: string;
}) {
  return (
    <button
      type="button"
      className={`flex h-14 min-w-[260px] items-center justify-between gap-4 rounded-xl border border-[#D7DEE7] bg-white px-5 text-left shadow-sm transition hover:border-[#9BB4C9] ${className}`}
    >
      <span className="flex items-center gap-3">
        <Icon className="h-6 w-6 text-[#123C5C]" />
        <span className="font-semibold text-[#123C5C]">{children}</span>
      </span>
      <ChevronDown className="h-5 w-5 text-[#123C5C]" />
    </button>
  );
}

function ChartTooltip({ active, payload, label }: any) {
  if (!active || !payload?.length) return null;

  return (
    <div className="rounded-xl border border-[#D7DEE7] bg-white px-4 py-3 shadow-lg">
      <p className="mb-2 font-bold text-[#123C5C]">{label}</p>
      <div className="space-y-1.5">
        {payload.map((entry: any) => {
          const product = products.find((item) => item.key === entry.dataKey);
          return (
            <div key={entry.dataKey} className="flex min-w-[150px] items-center justify-between gap-5 text-sm">
              <span className="flex items-center gap-2 text-[#45586B]">
                <span
                  className="h-2.5 w-2.5 rounded-full"
                  style={{ backgroundColor: entry.color }}
                />
                {product?.name ?? entry.dataKey}
              </span>
              <span className="font-bold text-[#123C5C]">₱{entry.value}/kg</span>
            </div>
          );
        })}
      </div>
    </div>
  );
}

export default function PriceTrends() {
  return (
    <div className="min-h-full bg-[#F8F7F1] p-4 sm:p-6 lg:p-8">
      <div className="mx-auto max-w-[1500px] space-y-5">
        {/* HEADER */}
        <header className="flex flex-col gap-5 xl:flex-row xl:items-center xl:justify-between">
          <div className="flex items-start gap-4">
            <div className="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-[#E7F7EC] text-[#16A05A]">
              <BarChart3 className="h-9 w-9" strokeWidth={2.5} />
            </div>
            <div>
              <h1 className="font-display text-4xl font-bold tracking-tight text-[#123C5C] sm:text-5xl">
                Price Trends
              </h1>
              <p className="mt-1 max-w-3xl text-sm text-[#123C5C] sm:text-base">
                Subaybayan ang market price trends mula July hanggang November para sa strategic planning.
              </p>
            </div>
          </div>

          <div className="flex flex-col gap-3 sm:flex-row">
            <SelectControl icon={CalendarDays} className="w-full sm:w-[260px]">
              July - November 2026
            </SelectControl>
            <SelectControl icon={LineChartIcon} className="w-full sm:w-[330px]">
              All Products
            </SelectControl>
          </div>
        </header>

        {/* TOP PRODUCT CARDS */}
        <section className="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
          {products.map((product) => {
            const change = percentChange(product.current, product.previous);
            return (
              <Card
                key={product.key}
                className={`border ${product.border} bg-white shadow-sm`}
              >
                <CardContent className="p-4">
                  <div className="flex items-center gap-4">
                    <ProductIcon product={product} />
                    <div className="min-w-0">
                      <p className="font-bold text-[#123C5C]">{product.name}</p>
                      <p className="mt-1 text-xl font-bold text-[#123C5C] sm:text-2xl">
                        {formatPrice(product.current)}
                      </p>
                      <div className="mt-1 flex items-center gap-2 text-sm">
                        <ChangeValue value={change} />
                      </div>
                      <p className="text-xs text-[#45586B]">vs previous period</p>
                    </div>
                  </div>
                </CardContent>
              </Card>
            );
          })}

          <Card className="border border-[#C8E7D4] bg-gradient-to-br from-white to-[#F0FAF3] shadow-sm md:col-span-2 xl:col-span-1">
            <CardContent className="p-4">
              <div className="flex items-start gap-3">
                <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-[#E7F7EC] text-[#16A05A]">
                  <Lightbulb className="h-8 w-8" />
                </div>
                <div className="min-w-0">
                  <div className="flex items-center gap-2">
                    <h2 className="font-bold text-[#123C5C]">Market Overview</h2>
                    <TrendingUp className="h-5 w-5 text-[#16A05A]" />
                  </div>
                  <p className="mt-1 text-sm leading-5 text-[#123C5C]">
                    Prices are generally trending upward across major agricultural and fishery products during the selected period.
                  </p>
                  <div className="mt-2 flex items-center justify-between rounded-xl border border-[#BFE7CE] bg-white px-3 py-2">
                    <span className="text-xs text-[#45586B]">Overall Trend</span>
                    <span className="font-bold text-[#16A05A]">Increasing</span>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>
        </section>

        {/* MAIN CHART + MOVEMENT */}
        <section className="grid gap-4 xl:grid-cols-[minmax(0,2.15fr)_minmax(330px,0.85fr)]">
          <Card className="overflow-hidden border-[#DDE3E8] bg-white shadow-sm">
            <CardHeader className="pb-2">
              <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                  <CardTitle className="text-xl text-[#123C5C] sm:text-2xl">
                    Market Price Trends (July - November)
                  </CardTitle>
                  <CardDescription>Average na buwanang presyo bawat kilo</CardDescription>
                </div>

                <div className="flex flex-wrap gap-2">
                  <button className="rounded-xl bg-[#075985] px-5 py-2 text-sm font-semibold text-white shadow-sm">
                    All
                  </button>
                  {products.map((product) => (
                    <button
                      key={product.key}
                      className={`rounded-xl px-4 py-2 text-sm font-semibold ${product.bg}`}
                      style={{ color: product.color }}
                    >
                      {product.name}
                    </button>
                  ))}
                </div>
              </div>
            </CardHeader>

            <CardContent className="px-3 pb-5 sm:px-5">
              <div className="h-[360px] w-full sm:h-[390px]">
                <ResponsiveContainer width="100%" height="100%">
                  <LineChart data={priceData} margin={{ top: 10, right: 18, left: 0, bottom: 10 }}>
                    <CartesianGrid strokeDasharray="3 3" stroke="#DCE2E7" />
                    <XAxis
                      dataKey="month"
                      tick={{ fill: '#64748B', fontSize: 13 }}
                      axisLine={{ stroke: '#CBD5E1' }}
                      tickLine={{ stroke: '#CBD5E1' }}
                    />
                    <YAxis
                      domain={[0, 200]}
                      ticks={[0, 50, 100, 150, 200]}
                      tick={{ fill: '#64748B', fontSize: 13 }}
                      axisLine={{ stroke: '#CBD5E1' }}
                      tickLine={{ stroke: '#CBD5E1' }}
                      label={{
                        value: 'Price (₱/kg)',
                        angle: -90,
                        position: 'insideLeft',
                        fill: '#64748B',
                        fontSize: 13,
                      }}
                    />
                    <Tooltip content={<ChartTooltip />} />
                    {products.map((product) => (
                      <Line
                        key={product.key}
                        type="monotone"
                        dataKey={product.key}
                        name={product.name}
                        stroke={product.color}
                        strokeWidth={2.5}
                        dot={{ r: 3.5, fill: '#FFFFFF', stroke: product.color, strokeWidth: 2 }}
                        activeDot={{ r: 5 }}
                      />
                    ))}
                  </LineChart>
                </ResponsiveContainer>
              </div>

              <div className="flex flex-wrap justify-center gap-x-7 gap-y-2 pt-1 text-sm">
                {products.map((product) => (
                  <div key={product.key} className="flex items-center gap-2" style={{ color: product.color }}>
                    <span className="h-2.5 w-2.5 rounded-full" style={{ backgroundColor: product.color }} />
                    {product.name}
                  </div>
                ))}
              </div>
            </CardContent>
          </Card>

          <Card className="border-[#DDE3E8] bg-white shadow-sm">
            <CardHeader className="pb-3">
              <CardTitle className="text-xl text-[#123C5C]">Price Movement (July - November)</CardTitle>
              <CardDescription>Change in average price from July to November</CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {products.map((product) => {
                const change = percentChange(product.current, product.previous);
                return (
                  <div key={product.key} className="rounded-xl border border-[#DDE3E8] bg-white p-3 shadow-sm">
                    <div className="flex items-center gap-3">
                      <ProductIcon product={product} size="sm" />
                      <div className="min-w-0 flex-1">
                        <p className="font-bold text-[#123C5C]">{product.name}</p>
                        <p className="text-sm text-[#123C5C]">
                          ₱{product.previous} → ₱{product.current}/kg
                        </p>
                      </div>
                      <ChangeValue value={change} />
                    </div>
                  </div>
                );
              })}
            </CardContent>
          </Card>
        </section>

        {/* LOWER INFORMATION ROW */}
        <section className="grid gap-4 lg:grid-cols-3">
          {/* CURRENT AVERAGES */}
          <Card className="border-[#DDE3E8] bg-white shadow-sm">
            <CardHeader>
              <CardTitle className="text-xl text-[#123C5C]">Kasalukuyang Average Prices</CardTitle>
              <CardDescription>Average market price per kilogram (July - November)</CardDescription>
            </CardHeader>
            <CardContent className="grid grid-cols-2 gap-3">
              {products.map((product) => {
                const change = percentChange(product.current, product.previous);
                return (
                  <div key={product.key} className="rounded-xl border border-[#DDE3E8] p-3">
                    <div className="flex items-center gap-2">
                      <ProductIcon product={product} size="sm" />
                      <span className="text-sm font-bold text-[#123C5C]">{product.name}</span>
                    </div>
                    <p className="mt-3 text-lg font-bold text-[#123C5C]">₱{product.current}/kg</p>
                    <p className="text-xs text-[#45586B]">Average price</p>
                    <div className="mt-2">
                      <ChangeValue value={change} />
                    </div>
                  </div>
                );
              })}
            </CardContent>
          </Card>

          {/* HARBORAI INSIGHT */}
          <Card className="overflow-hidden border-[#9DDCE9] bg-gradient-to-br from-white via-[#F1FBFD] to-[#DDF5FA] shadow-sm">
            <CardContent className="relative h-full p-5 sm:p-6">
              <div className="relative z-10">
                <div className="flex items-center justify-between gap-3">
                  <div className="flex items-center gap-3">
                    <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-[#E4F7FA] text-[#075985]">
                      <Info className="h-7 w-7" />
                    </div>
                    <h2 className="text-xl font-bold text-[#123C5C]">HarborAI Market Insight</h2>
                  </div>
                  <span className="rounded-full bg-[#D5F3F0] px-3 py-1 text-xs font-semibold text-[#0F766E]">
                    Based on current data
                  </span>
                </div>

                <p className="mt-5 text-base leading-6 text-[#123C5C]">
                  Rice and fish prices show a steady upward trend during the selected period. This may indicate increasing demand as we approach the last quarter. Buyers may consider securing supplies early to ensure stable prices and availability.
                </p>
              </div>

              <div className="pointer-events-none absolute -bottom-12 -left-8 h-28 w-[120%] rounded-[50%] bg-[#B9EAF3]/60" />
              <div className="pointer-events-none absolute -bottom-16 -right-8 h-24 w-[80%] rounded-[50%] bg-[#83D9E8]/45" />
            </CardContent>
          </Card>

          {/* MARKET GUIDE */}
          <Card className="border-[#DDE3E8] bg-white shadow-sm">
            <CardHeader className="pb-2">
              <CardTitle className="flex items-center gap-2 text-xl text-[#123C5C]">
                <BookOpen className="h-6 w-6 text-[#123C5C]" />
                Gabay sa Market
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="flex gap-3">
                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#E7F7EC] text-[#16A05A]">
                  <TrendingUp className="h-5 w-5" />
                </div>
                <div>
                  <p className="font-bold text-[#123C5C]">Subaybayan ang price trends</p>
                  <p className="text-sm text-[#45586B]">Tumutulong ito sa mas matalinong pagdedesisyon.</p>
                </div>
              </div>

              <div className="flex gap-3">
                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#E7F7EC] text-[#16A05A]">
                  <CalendarDays className="h-5 w-5" />
                </div>
                <div>
                  <p className="font-bold text-[#123C5C]">Isaalang-alang ang seasonal changes</p>
                  <p className="text-sm text-[#45586B]">Ang ilang produkto ay may peak at low season.</p>
                </div>
              </div>

              <div className="flex gap-3">
                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#E7F7EC] text-[#16A05A]">
                  <Users className="h-5 w-5" />
                </div>
                <div>
                  <p className="font-bold text-[#123C5C]">Hambingin ang prices ng suppliers</p>
                  <p className="text-sm text-[#45586B]">Makakatulong ito upang makahanap ng mas magandang alok.</p>
                </div>
              </div>

              <div className="flex gap-3">
                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#E7F7EC] text-[#16A05A]">
                  <BarChart3 className="h-5 w-5" />
                </div>
                <div>
                  <p className="font-bold text-[#123C5C]">Magplano ng bulk purchases</p>
                  <p className="text-sm text-[#45586B]">Mas malaking tipid kapag maagang nagplano.</p>
                </div>
              </div>
            </CardContent>
          </Card>
        </section>
      </div>
    </div>
  );
}
