import { DashboardText, useDashboardCopy } from '../../../i18n/DashboardCopy';
import React, { useEffect, useMemo, useState } from "react";
import {
  BadgeCheck,
  CheckCircle2,
  Edit3,
  Fish,
  Hash,
  Mail,
  MapPin,
  Phone,
  Save,
  ShieldCheck,
  Sprout,
  UserRound,
  X,
} from "lucide-react";
import { request } from "../../../../lib/api";

type Registry = {
  rsbsa_number: string;
  full_name: string;
  sex: string | null;
  barangay: string | null;
  farm_location: string | null;
  area_ha: string | number | null;
  status: string;
};

type Producer = {
  id?: number;
  producer_id?: number;
  name?: string;
  email?: string;
  contact_number?: string;
  rsbsa_number?: string;
  location?: string;
  primary_product_type?: string;
  verification_status?: string;
  producer_type?: string;
  registry?: Registry | null;
};

const emptyProducer: Producer = {
  name: "Producer",
  email: "",
  contact_number: "",
  rsbsa_number: "",
  location: "",
  primary_product_type: "",
  verification_status: "",
  producer_type: "Producer",
};

export default function MyProfile() {
  const copy = useDashboardCopy();
  const [isEditing, setIsEditing] = useState(false);
  const [producer, setProducer] = useState<Producer | null>(null);
  const [draftProducer, setDraftProducer] = useState<Producer>(emptyProducer);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const [saveError, setSaveError] = useState("");
  const [saved, setSaved] = useState(false);

  useEffect(() => {
    const controller = new AbortController();

    const fetchProfile = async () => {
      try {
        const result = await request("/api/producer/profile", { signal: controller.signal });
        const profile: Producer = result?.data;
        setProducer(profile);
        setDraftProducer(profile);
        setError(null);
      } catch (err) {
        if ((err as any)?.name === "AbortError") return;
        console.error("Error fetching profile:", err);
        setError(err instanceof Error ? err.message : "Failed to load profile");
      } finally {
        if (!controller.signal.aborted) setLoading(false);
      }
    };

    fetchProfile();
    return () => controller.abort();
  }, []);

  const displayName = producer?.name || "Producer";

  const initials = useMemo(() => {
    const parts = displayName.trim().split(/\s+/).filter(Boolean);

    if (parts.length === 0) return "P";

    if (parts.length === 1) {
      return parts[0].slice(0, 2).toUpperCase();
    }

    return `${parts[0][0]}${parts[parts.length - 1][0]}`.toUpperCase();
  }, [displayName]);

  const isVerified =
    String(producer?.verification_status || "").toLowerCase() === "verified";
  const verificationStatus = String(producer?.verification_status || "Pending");

  const updateField = (field: keyof Producer, value: string) => {
    setDraftProducer((current) => ({
      ...current,
      [field]: value,
    }));
  };

  const startEditing = () => {
    setDraftProducer(producer || emptyProducer);
    setSaveError("");
    setSaved(false);
    setIsEditing(true);
  };

  const cancelEditing = () => {
    setDraftProducer(producer || emptyProducer);
    setSaveError("");
    setIsEditing(false);
  };

  const saveChanges = () => {
    if (saving) return;
    setSaving(true);
    setSaveError("");
    setSaved(false);
    void request("/api/producer/profile", {
      method: "PUT",
      body: {
        email: draftProducer.email?.trim(),
        contact_number: draftProducer.contact_number?.trim() || null,
      },
    })
      .then((result) => {
        setProducer(result?.data);
        setDraftProducer(result?.data);
        setIsEditing(false);
        setSaved(true);
      })
      .catch((failure: unknown) => {
        const error = failure as { message?: string; errors?: Record<string, string[]> };
        const validation = Object.values(error.errors || {}).flat().join(" ");
        setSaveError(validation || error.message || "Could not save your profile.");
      })
      .finally(() => {
        setSaving(false);
      });
  };

  if (loading) {
    return (
      <div className="min-h-full bg-muted p-4 sm:p-6 lg:p-8">
        <div className="mx-auto max-w-7xl">
          <div className="animate-pulse space-y-4 sm:space-y-6">
            <div className="h-12 w-64 rounded-xl bg-card/70" />
            <div className="h-44 rounded-3xl bg-card/70" />
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
              {[1, 2, 3, 4, 5, 6].map((item) => (
                <div key={item} className="h-28 rounded-2xl bg-card/70" />
              ))}
            </div>
          </div>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="min-h-full bg-muted p-4 sm:p-6 lg:p-8">
        <div className="mx-auto max-w-7xl rounded-2xl border border-red-200 bg-card p-6 text-red-700 shadow-sm">
          <h2 className="font-bold"><DashboardText text="Hindi ma-load ang profile" /></h2>
          <p className="mt-1 text-sm">{error}</p>
        </div>
      </div>
    );
  }

  if (!producer) {
    return (
      <div className="min-h-full bg-muted p-4 sm:p-6 lg:p-8">
        <div className="mx-auto max-w-7xl rounded-2xl border border-border bg-card p-6 text-muted-foreground shadow-sm"><DashboardText text="Hindi makita ang profile." /></div>
      </div>
    );
  }

  const infoCards = [
    {
      label: "Email",
      value: producer.email || "—",
      icon: Mail,
      iconBackground: "bg-[#E2F0FF]",
      iconColor: "text-[#1976D2]",
    },
    {
      label: "Contact",
      value: producer.contact_number || "—",
      icon: Phone,
      iconBackground: "bg-[#E4F5E9]",
      iconColor: "text-[#159447]",
    },
    {
      label: "RSBSA Number",
      value: producer.rsbsa_number || "—",
      icon: Hash,
      iconBackground: "bg-[#FFF0D5]",
      iconColor: "text-[#E99A18]",
    },
    {
      label: "Lokasyon",
      value: producer.location || "—",
      icon: MapPin,
      iconBackground: "bg-[#DFF4EA]",
      iconColor: "text-[#168B65]",
    },
    {
      label: "Pangunahing Produkto",
      value: producer.primary_product_type || "—",
      icon: Fish,
      iconBackground: "bg-[#DDF4F7]",
      iconColor: "text-[#138BA3]",
    },
    {
      label: "Producer Type",
      value: producer.producer_type || "Producer",
      icon: UserRound,
      iconBackground: "bg-[#EEE5FF]",
      iconColor: "text-[#7048D7]",
    },
  ];

  return (
    <div className="min-h-full overflow-x-hidden bg-muted p-4 sm:p-6 lg:p-8">
      <div className="mx-auto max-w-7xl space-y-4 sm:space-y-6">
        {/* PAGE HEADER */}
        <section className="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
          <div>
            <h1 className="font-display text-4xl font-black tracking-tight text-foreground sm:text-5xl"><DashboardText text="My Profile" /></h1>
            <p className="mt-1 text-sm text-muted-foreground sm:text-base"><DashboardText text="Pamahalaan ang iyong account at impormasyon ng enterprise" /></p>
          </div>

          <div className="flex flex-col items-stretch gap-2 sm:min-w-[285px]">
            {!isEditing ? (
              <button
                type="button"
                onClick={startEditing}
                className="inline-flex items-center justify-center gap-2 rounded-xl bg-[#1769E8] px-6 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-[#1258C7] hover:shadow-md"
              >
                <Edit3 className="h-4 w-4" /><DashboardText text="I-edit ang Profile" /></button>
            ) : (
              <div className="flex flex-col gap-2 sm:flex-row">
                <button
                  type="button"
                  onClick={cancelEditing}
                  disabled={saving}
                  className="inline-flex flex-1 items-center justify-center gap-2 rounded-xl border border-[#D6D9D4] bg-card px-5 py-3 text-sm font-bold text-muted-foreground transition hover:bg-[#F8F8F4] disabled:opacity-60"
                >
                  <X className="h-4 w-4" /><DashboardText text="Kanselahin" /></button>

                <button
                  type="button"
                  onClick={saveChanges}
                  disabled={saving}
                  className="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-[#159447] px-5 py-3 text-sm font-bold text-white transition hover:bg-[#107638] disabled:opacity-60"
                >
                  <Save className="h-4 w-4" />
                  {saving ? copy("Sine-save…") : copy("I-save ang Changes")}
                </button>
              </div>
            )}

            {!isEditing && (
              <p className="text-center text-xs leading-relaxed text-muted-foreground sm:text-left"><DashboardText text="Panatilihing updated ang iyong impormasyon para mas maraming bumili at makakita sa iyong produkto." /></p>
            )}
          </div>
        </section>
        {saved && (
          <p role="status" className="rounded-lg bg-[#EAF8E9] p-3 text-sm font-semibold text-[#13843E]"><DashboardText text="Profile updates saved to your account." /></p>
        )}

        {/* PROFILE HERO */}
        <section className="overflow-hidden rounded-3xl border border-[#DDE3D9] bg-card shadow-sm">
          <div className="grid lg:grid-cols-[1fr_320px]">
            <div className="flex flex-col gap-3 p-4 sm:gap-5 sm:p-7 lg:flex-row lg:items-center lg:p-8">
              {/* AVATAR */}
              <div className="relative mx-auto shrink-0 lg:mx-0">
                <div className="flex h-28 w-28 items-center justify-center rounded-full border-4 border-[#F1ECE1] bg-[#E8DFDB] text-4xl font-black text-foreground shadow-sm sm:h-32 sm:w-32">
                  {initials}
                </div>

                <div
                  className="absolute bottom-0 right-0 flex h-9 w-9 items-center justify-center rounded-full border-4 border-white bg-[#159447] text-white shadow-sm"
                  title={copy("Producer profile")}
                >
                  <Sprout className="h-4 w-4" />
                </div>
              </div>

              {/* IDENTITY */}
              <div className="min-w-0 text-center lg:text-left">
                <div className="flex flex-wrap items-center justify-center gap-2 lg:justify-start">
                  <h2 className="text-3xl font-black tracking-tight text-foreground sm:text-4xl">
                    {producer.name}
                  </h2>

                  {isVerified && (
                    <span className="inline-flex items-center gap-1.5 rounded-full bg-[#DDF6E6] px-3 py-1.5 text-sm font-bold text-[#13843E]">
                      <BadgeCheck className="h-4 w-4" /><DashboardText text="Verified" /></span>
                  )}
                </div>

                <p className="mt-2 inline-flex items-center gap-2 text-lg font-semibold text-muted-foreground">
                  <Sprout className="h-5 w-5 text-[#159447]" />
                  {producer.producer_type || copy("Producer")}
                </p>

                <p className="mt-3 text-base italic text-[#5B7184]">
                  "Lokal na Produksyon, Mas Maunlad na Kinabukasan."
                </p>
              </div>
            </div>

            {/* RIGHT MESSAGE PANEL */}
            <div className="border-t border-[#E7EAE5] bg-[#FBFCF8] p-4 sm:p-6 lg:border-l lg:border-t-0 lg:p-8">
              <div className="flex items-start gap-4">
                <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-[#DDF3E4]">
                  <Sprout className="h-6 w-6 text-[#159447]" />
                </div>

                <div>
                  <p className="text-lg font-black leading-tight text-[#13843E]"><DashboardText text="Proud to be a" /><br />
                    HarborAI Producer
                  </p>

                  <div className="mt-3 h-0.5 w-20 bg-[#F0B52B]" />

                  <p className="mt-3 text-sm leading-relaxed text-muted-foreground">
                    Mas Malakas na Lokal
                    <br />
                    Mas Maunlad na Aparri
                  </p>
                </div>
              </div>
            </div>
          </div>
        </section>

        {/* INFORMATION CARDS */}
        <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          {infoCards.map((card) => {
            const Icon = card.icon;

            return (
              <article
                key={card.label}
                className="rounded-2xl border border-[#E0E3DD] bg-card p-3 shadow-sm transition sm:p-5 duration-200 hover:-translate-y-0.5 hover:shadow-md"
              >
                <div className="flex items-center gap-3 sm:gap-4">
                  <div
                    className={`flex h-10 w-10 shrink-0 sm:h-14 sm:w-14 items-center justify-center rounded-full ${card.iconBackground}`}
                  >
                    <Icon className={`h-7 w-7 ${card.iconColor}`} />
                  </div>

                  <div className="min-w-0">
                    <p className="text-sm font-bold text-foreground">
                      {card.label}
                    </p>
                    <p className="mt-1 break-words text-base text-foreground sm:text-lg">
                      {card.value}
                    </p>
                  </div>
                </div>
              </article>
            );
          })}
        </section>

        {/* MAO REGISTRY INFORMATION */}
        {producer.registry && (
          <section className="rounded-3xl border border-[#DCE7D8] bg-card p-4 shadow-sm sm:p-7">
            <div className="flex items-center gap-3">
              <ShieldCheck className="h-6 w-6 text-[#159447]" />
              <h2 className="text-xl font-black text-foreground"><DashboardText text="MAO Registry Information" /></h2>
            </div>
            <dl className="mt-3 grid gap-3 sm:mt-5 sm:gap-4 sm:grid-cols-2 lg:grid-cols-3">
              <div>
                <dt className="text-xs font-bold uppercase tracking-wide text-muted-foreground">
                  RSBSA Number
                </dt>
                <dd className="mt-1 text-sm text-foreground">
                  {producer.registry.rsbsa_number}
                </dd>
              </div>
              <div>
                <dt className="text-xs font-bold uppercase tracking-wide text-muted-foreground"><DashboardText text="Registered Name" /></dt>
                <dd className="mt-1 text-sm text-foreground">
                  {producer.registry.full_name}
                </dd>
              </div>
              <div>
                <dt className="text-xs font-bold uppercase tracking-wide text-muted-foreground"><DashboardText text="Sex" /></dt>
                <dd className="mt-1 text-sm text-foreground">
                  {producer.registry.sex || "—"}
                </dd>
              </div>
              <div>
                <dt className="text-xs font-bold uppercase tracking-wide text-muted-foreground">
                  Barangay
                </dt>
                <dd className="mt-1 text-sm text-foreground">
                  {producer.registry.barangay || "—"}
                </dd>
              </div>
              <div>
                <dt className="text-xs font-bold uppercase tracking-wide text-muted-foreground"><DashboardText text="Farm Location" /></dt>
                <dd className="mt-1 text-sm text-foreground">
                  {producer.registry.farm_location || "—"}
                </dd>
              </div>
              <div>
                <dt className="text-xs font-bold uppercase tracking-wide text-muted-foreground"><DashboardText text="Registered Area" /></dt>
                <dd className="mt-1 text-sm text-foreground">
                  {producer.registry.area_ha == null
                    ? "—"
                    : `${producer.registry.area_ha} ha`}
                </dd>
              </div>
              <div>
                <dt className="text-xs font-bold uppercase tracking-wide text-muted-foreground"><DashboardText text="Registry Status" /></dt>
                <dd className="mt-1 text-sm font-semibold text-[#13843E]">
                  {producer.registry.status}
                </dd>
              </div>
            </dl>
            <p className="mt-5 border-t border-border pt-4 text-xs leading-relaxed text-muted-foreground"><DashboardText text="This information is based on the registered producer dataset supplied to HarborAI and cannot be edited directly from your account." /></p>
          </section>
        )}

        {/* LOWER INFORMATION */}
        <section className="grid gap-3 sm:gap-5 lg:grid-cols-2">
          {/* VERIFICATION */}
          <article className="overflow-hidden rounded-3xl border border-[#DCE7D8] bg-card shadow-sm">
            <div className="p-4 sm:p-7">
              <div className="flex items-start gap-4">
                <div className="flex h-10 w-10 shrink-0 sm:h-14 sm:w-14 items-center justify-center rounded-2xl bg-[#D9F4E1]">
                  <ShieldCheck className="h-8 w-8 text-[#159447]" />
                </div>

                <div>
                  <h3 className="text-xl font-black text-foreground"><DashboardText text="Account Verification" /></h3>
                  <p className="mt-1 text-sm leading-relaxed text-muted-foreground">
                    {isVerified
                      ? copy("Naaprubahan ang iyong producer account. Maaari kang mag-post ng produkto at makipagtransaksyon sa HarborAI.")
                      : verificationStatus.toLowerCase() === "rejected"
                        ? copy("Hindi naaprubahan ang iyong application. Makipag-ugnayan sa administrator kung kailangan mo ng paglilinaw.")
                        : copy("Naghihintay pa ng administrator review ang iyong producer application. Magiging available ang producer features kapag naaprubahan.")}
                  </p>
                </div>
              </div>

              <div className="mt-5 rounded-2xl bg-[#EAF8E9] p-4">
                <div className="flex items-center gap-3">
                  <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#159447] text-white">
                    <CheckCircle2 className="h-6 w-6" />
                  </div>

                  <div>
                    <p className="font-black text-[#13843E]">
                      {producer.verification_status || copy("Pending")}
                    </p>
                    <p className="text-sm text-[#55716A]"><DashboardText text="Ang verification status ng iyong producer account." /></p>
                  </div>
                </div>
              </div>
            </div>
          </article>

          {/* PROFILE GUIDANCE */}
          <article className="relative overflow-hidden rounded-3xl border border-[#D7E6ED] bg-[#F4FAFD] shadow-sm">
            <div className="p-4 sm:p-7">
              <div className="flex items-start gap-4">
                <div className="flex h-10 w-10 shrink-0 sm:h-14 sm:w-14 items-center justify-center rounded-2xl bg-[#DDF3F1]">
                  <Sprout className="h-7 w-7 text-[#138B78]" />
                </div>

                <div>
                  <h3 className="text-xl font-black text-foreground"><DashboardText text="Panatilihing Updated ang Iyong Profile" /></h3>
                  <p className="mt-1 text-sm leading-relaxed text-muted-foreground"><DashboardText text="Ang kumpletong impormasyon ay nakakatulong upang mas mapagkatiwalaan ang iyong account at mas maraming oportunidad sa merkado." /></p>
                </div>
              </div>

              <div className="mt-5 space-y-3">
                {[
                  "I-update ang iyong contact information",
                  "Panatilihing tama ang iyong lokasyon",
                  "Ilagay ang iyong pangunahing produkto",
                ].map((item) => (
                  <div key={item} className="flex items-center gap-3">
                    <CheckCircle2 className="h-5 w-5 shrink-0 text-[#159447]" />
                    <span className="text-sm font-medium text-muted-foreground">
                      {item}
                    </span>
                  </div>
                ))}
              </div>
            </div>

            <div className="pointer-events-none absolute -bottom-8 -right-5 opacity-10">
              <Sprout className="h-40 w-40 text-[#159447]" />
            </div>
          </article>
        </section>
      </div>

      {/* EDIT MODAL */}
      {isEditing && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center bg-[#123C5C]/45 p-4 backdrop-blur-sm"
          role="dialog"
          aria-modal="true"
          aria-label={copy("Edit Producer Profile")}
        >
          <div className="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-3xl bg-card shadow-2xl">
            <div className="sticky top-0 z-10 flex items-center justify-between border-b border-border bg-card px-5 py-4 sm:px-7">
              <div>
                <h2 className="text-xl font-black text-foreground"><DashboardText text="I-edit ang Profile" /></h2>
                <p className="text-sm text-muted-foreground"><DashboardText text="Update your producer information." /></p>
              </div>

              <button
                type="button"
                onClick={cancelEditing}
                disabled={saving}
                className="rounded-xl p-2 text-muted-foreground transition hover:bg-background disabled:opacity-60"
                aria-label={copy("Close edit profile")}
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            <div className="grid gap-5 p-5 sm:grid-cols-2 sm:p-7">
              <label className="sm:col-span-2">
                <span className="mb-2 block text-sm font-bold text-foreground"><DashboardText text="Full Name" /></span>
                <input
                  value={draftProducer.name || ""}
                  readOnly
                  aria-describedby="official-profile-fields"
                  className="w-full cursor-not-allowed rounded-xl border border-[#D6DDD6] bg-[#F2F4F1] px-4 py-3 text-sm text-muted-foreground"
                />
              </label>
              <p
                id="official-profile-fields"
                className="text-xs leading-relaxed text-muted-foreground sm:col-span-2 sm:-mt-3"
              ><DashboardText text="Name, RSBSA number, location, livelihood, producer type, and verification status are official or registry-backed fields. Contact an administrator to request a correction." /></p>

              <label>
                <span className="mb-2 block text-sm font-bold text-foreground">
                  Email
                </span>
                <input
                  type="email"
                  value={draftProducer.email || ""}
                  onChange={(event) =>
                    updateField("email", event.target.value)
                  }
                  className="w-full rounded-xl border border-[#D6DDD6] bg-[#FBFCF9] px-4 py-3 text-sm text-foreground outline-none transition focus:border-[#159447] focus:ring-2 focus:ring-[#159447]/15"
                />
              </label>

              <label>
                <span className="mb-2 block text-sm font-bold text-foreground"><DashboardText text="Contact" /></span>
                <input
                  value={draftProducer.contact_number || ""}
                  onChange={(event) =>
                    updateField("contact_number", event.target.value)
                  }
                  className="w-full rounded-xl border border-[#D6DDD6] bg-[#FBFCF9] px-4 py-3 text-sm text-foreground outline-none transition focus:border-[#159447] focus:ring-2 focus:ring-[#159447]/15"
                />
              </label>

              <label>
                <span className="mb-2 block text-sm font-bold text-foreground">
                  RSBSA Number
                </span>
                <input
                  value={draftProducer.rsbsa_number || ""}
                  readOnly
                  aria-describedby="official-profile-fields"
                  className="w-full cursor-not-allowed rounded-xl border border-[#D6DDD6] bg-[#F2F4F1] px-4 py-3 text-sm text-muted-foreground"
                />
              </label>

              <label>
                <span className="mb-2 block text-sm font-bold text-foreground"><DashboardText text="Lokasyon" /></span>
                <input
                  value={draftProducer.location || ""}
                  readOnly
                  aria-describedby="official-profile-fields"
                  className="w-full cursor-not-allowed rounded-xl border border-[#D6DDD6] bg-[#F2F4F1] px-4 py-3 text-sm text-muted-foreground"
                />
              </label>

              <label>
                <span className="mb-2 block text-sm font-bold text-foreground"><DashboardText text="Pangunahing Produkto" /></span>
                <input
                  value={draftProducer.primary_product_type || ""}
                  readOnly
                  aria-describedby="official-profile-fields"
                  className="w-full cursor-not-allowed rounded-xl border border-[#D6DDD6] bg-[#F2F4F1] px-4 py-3 text-sm text-muted-foreground"
                />
              </label>
            </div>

            {saveError && (
              <p
                role="alert"
                className="mx-5 mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-800 sm:mx-7"
              >
                {saveError}
              </p>
            )}
            <div className="flex flex-col-reverse gap-3 border-t border-border bg-[#FBFCF9] px-5 py-4 sm:flex-row sm:justify-end sm:px-7">
              <button
                type="button"
                onClick={cancelEditing}
                disabled={saving}
                className="rounded-xl border border-[#D6DDD6] px-5 py-3 text-sm font-bold text-muted-foreground transition hover:bg-card disabled:opacity-60"
              ><DashboardText text="Kanselahin" /></button>

              <button
                type="button"
                onClick={saveChanges}
                disabled={saving}
                className="inline-flex items-center justify-center gap-2 rounded-xl bg-[#159447] px-5 py-3 text-sm font-bold text-white transition hover:bg-[#107638] disabled:cursor-wait disabled:opacity-60"
              >
                <Save className="h-4 w-4" />
                {saving ? copy("Sine-save…") : copy("I-save ang Changes")}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
