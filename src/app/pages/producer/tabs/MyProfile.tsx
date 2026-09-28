import React, { useEffect, useMemo, useState } from "react";
import {
  BadgeCheck,
  Building2,
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
  const [isEditing, setIsEditing] = useState(false);
  const [producer, setProducer] = useState<Producer | null>(null);
  const [draftProducer, setDraftProducer] = useState<Producer>(emptyProducer);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const fetchProfile = async () => {
      try {
        const authToken = localStorage.getItem("authToken");

        const headers: HeadersInit = {
          Accept: "application/json",
          "Content-Type": "application/json",
        };

        if (authToken) {
          headers.Authorization = `Bearer ${authToken}`;
        }

        const response = await fetch("/api/producer/dashboard", {
          headers,
        });

        if (!response.ok) {
          throw new Error("Failed to fetch producer profile");
        }

        const data = await response.json();

        const profile: Producer = data?.producer ?? data?.data?.producer ?? data;

        setProducer(profile);
        setDraftProducer(profile);
        setError(null);
      } catch (err) {
        console.error("Error fetching profile:", err);
        setError(
          err instanceof Error ? err.message : "Failed to load profile"
        );
      } finally {
        setLoading(false);
      }
    };

    fetchProfile();
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

  const updateField = (field: keyof Producer, value: string) => {
    setDraftProducer((current) => ({
      ...current,
      [field]: value,
    }));
  };

  const startEditing = () => {
    setDraftProducer(producer || emptyProducer);
    setIsEditing(true);
  };

  const cancelEditing = () => {
    setDraftProducer(producer || emptyProducer);
    setIsEditing(false);
  };

  const saveChanges = () => {
    /*
     * The supplied component does not define a confirmed producer-profile
     * update API endpoint. Therefore this keeps the existing profile-update
     * behavior safe by updating the displayed frontend state only.
     *
     * Add your Laravel PUT/PATCH endpoint here when the backend is ready.
     */
    setProducer(draftProducer);
    setIsEditing(false);
  };

  if (loading) {
    return (
      <div className="min-h-full bg-[#F6F2E7] p-4 sm:p-6 lg:p-8">
        <div className="mx-auto max-w-7xl">
          <div className="animate-pulse space-y-6">
            <div className="h-12 w-64 rounded-xl bg-white/70" />
            <div className="h-44 rounded-3xl bg-white/70" />
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
              {[1, 2, 3, 4, 5, 6].map((item) => (
                <div key={item} className="h-28 rounded-2xl bg-white/70" />
              ))}
            </div>
          </div>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="min-h-full bg-[#F6F2E7] p-4 sm:p-6 lg:p-8">
        <div className="mx-auto max-w-7xl rounded-2xl border border-red-200 bg-white p-6 text-red-700 shadow-sm">
          <h2 className="font-bold">Hindi ma-load ang profile</h2>
          <p className="mt-1 text-sm">{error}</p>
        </div>
      </div>
    );
  }

  if (!producer) {
    return (
      <div className="min-h-full bg-[#F6F2E7] p-4 sm:p-6 lg:p-8">
        <div className="mx-auto max-w-7xl rounded-2xl border border-[#E7E1D0] bg-white p-6 text-[#45586B] shadow-sm">
          Hindi makita ang profile.
        </div>
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
    <div className="min-h-full overflow-x-hidden bg-[#F6F2E7] p-4 sm:p-6 lg:p-8">
      <div className="mx-auto max-w-7xl space-y-6">
        {/* PAGE HEADER */}
        <section className="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
          <div>
            <h1 className="font-display text-4xl font-black tracking-tight text-[#123C5C] sm:text-5xl">
              My Profile
            </h1>
            <p className="mt-1 text-sm text-[#45586B] sm:text-base">
              Pamahalaan ang iyong account at impormasyon ng enterprise
            </p>
          </div>

          <div className="flex flex-col items-stretch gap-2 sm:min-w-[285px]">
            {!isEditing ? (
              <button
                type="button"
                onClick={startEditing}
                className="inline-flex items-center justify-center gap-2 rounded-xl bg-[#1769E8] px-6 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-[#1258C7] hover:shadow-md"
              >
                <Edit3 className="h-4 w-4" />
                I-edit ang Profile
              </button>
            ) : (
              <div className="flex flex-col gap-2 sm:flex-row">
                <button
                  type="button"
                  onClick={cancelEditing}
                  className="inline-flex flex-1 items-center justify-center gap-2 rounded-xl border border-[#D6D9D4] bg-white px-5 py-3 text-sm font-bold text-[#45586B] transition hover:bg-[#F8F8F4]"
                >
                  <X className="h-4 w-4" />
                  Kanselahin
                </button>

                <button
                  type="button"
                  onClick={saveChanges}
                  className="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-[#159447] px-5 py-3 text-sm font-bold text-white transition hover:bg-[#107638]"
                >
                  <Save className="h-4 w-4" />
                  I-save ang Changes
                </button>
              </div>
            )}

            {!isEditing && (
              <p className="text-center text-xs leading-relaxed text-[#607080] sm:text-left">
                Panatilihing updated ang iyong impormasyon para mas maraming
                bumili at makakita sa iyong produkto.
              </p>
            )}
          </div>
        </section>

        {/* PROFILE HERO */}
        <section className="overflow-hidden rounded-3xl border border-[#DDE3D9] bg-white shadow-sm">
          <div className="grid lg:grid-cols-[1fr_320px]">
            <div className="flex flex-col gap-5 p-5 sm:p-7 lg:flex-row lg:items-center lg:p-8">
              {/* AVATAR */}
              <div className="relative mx-auto shrink-0 lg:mx-0">
                <div className="flex h-28 w-28 items-center justify-center rounded-full border-4 border-[#F1ECE1] bg-[#E8DFDB] text-4xl font-black text-[#123C5C] shadow-sm sm:h-32 sm:w-32">
                  {initials}
                </div>

                <div
                  className="absolute bottom-0 right-0 flex h-9 w-9 items-center justify-center rounded-full border-4 border-white bg-[#159447] text-white shadow-sm"
                  title="Producer profile"
                >
                  <Sprout className="h-4 w-4" />
                </div>
              </div>

              {/* IDENTITY */}
              <div className="min-w-0 text-center lg:text-left">
                <div className="flex flex-wrap items-center justify-center gap-2 lg:justify-start">
                  <h2 className="text-3xl font-black tracking-tight text-[#123C5C] sm:text-4xl">
                    {producer.name}
                  </h2>

                  {isVerified && (
                    <span className="inline-flex items-center gap-1.5 rounded-full bg-[#DDF6E6] px-3 py-1.5 text-sm font-bold text-[#13843E]">
                      <BadgeCheck className="h-4 w-4" />
                      Verified
                    </span>
                  )}
                </div>

                <p className="mt-2 inline-flex items-center gap-2 text-lg font-semibold text-[#45586B]">
                  <Sprout className="h-5 w-5 text-[#159447]" />
                  {producer.producer_type || "Producer"}
                </p>

                <p className="mt-3 text-base italic text-[#5B7184]">
                  “Lokal na Produksyon, Mas Maunlad na Kinabukasan.”
                </p>
              </div>
            </div>

            {/* RIGHT MESSAGE PANEL */}
            <div className="border-t border-[#E7EAE5] bg-[#FBFCF8] p-6 lg:border-l lg:border-t-0 lg:p-8">
              <div className="flex items-start gap-4">
                <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-[#DDF3E4]">
                  <Sprout className="h-6 w-6 text-[#159447]" />
                </div>

                <div>
                  <p className="text-lg font-black leading-tight text-[#13843E]">
                    Proud to be a
                    <br />
                    HarborAI Producer
                  </p>

                  <div className="mt-3 h-0.5 w-20 bg-[#F0B52B]" />

                  <p className="mt-3 text-sm leading-relaxed text-[#607080]">
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
                className="rounded-2xl border border-[#E0E3DD] bg-white p-5 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-md"
              >
                <div className="flex items-center gap-4">
                  <div
                    className={`flex h-14 w-14 shrink-0 items-center justify-center rounded-full ${card.iconBackground}`}
                  >
                    <Icon className={`h-7 w-7 ${card.iconColor}`} />
                  </div>

                  <div className="min-w-0">
                    <p className="text-sm font-bold text-[#123C5C]">
                      {card.label}
                    </p>
                    <p className="mt-1 break-words text-base text-[#123C5C] sm:text-lg">
                      {card.value}
                    </p>
                  </div>
                </div>
              </article>
            );
          })}
        </section>

        {/* LOWER INFORMATION */}
        <section className="grid gap-5 lg:grid-cols-2">
          {/* VERIFICATION */}
          <article className="overflow-hidden rounded-3xl border border-[#DCE7D8] bg-white shadow-sm">
            <div className="p-6 sm:p-7">
              <div className="flex items-start gap-4">
                <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-[#D9F4E1]">
                  <ShieldCheck className="h-8 w-8 text-[#159447]" />
                </div>

                <div>
                  <h3 className="text-xl font-black text-[#123C5C]">
                    Account Verification
                  </h3>
                  <p className="mt-1 text-sm leading-relaxed text-[#50687B]">
                    Ang iyong account ay na-verify at aktibo. Maaari ka nang
                    mag-post ng produkto at makipagtransaksyon sa mga buyer sa
                    HarborAI.
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
                      {producer.verification_status || "Verified"}
                    </p>
                    <p className="text-sm text-[#55716A]">
                      Ang verification status ng iyong producer account.
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </article>

          {/* PROFILE GUIDANCE */}
          <article className="relative overflow-hidden rounded-3xl border border-[#D7E6ED] bg-[#F4FAFD] shadow-sm">
            <div className="p-6 sm:p-7">
              <div className="flex items-start gap-4">
                <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-[#DDF3F1]">
                  <Sprout className="h-7 w-7 text-[#138B78]" />
                </div>

                <div>
                  <h3 className="text-xl font-black text-[#123C5C]">
                    Panatilihing Updated ang Iyong Profile
                  </h3>
                  <p className="mt-1 text-sm leading-relaxed text-[#50687B]">
                    Ang kumpletong impormasyon ay nakakatulong upang mas
                    mapagkatiwalaan ang iyong account at mas maraming
                    oportunidad sa merkado.
                  </p>
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
                    <span className="text-sm font-medium text-[#45586B]">
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
          aria-label="Edit Producer Profile"
        >
          <div className="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-3xl bg-white shadow-2xl">
            <div className="sticky top-0 z-10 flex items-center justify-between border-b border-[#E7E1D0] bg-white px-5 py-4 sm:px-7">
              <div>
                <h2 className="text-xl font-black text-[#123C5C]">
                  I-edit ang Profile
                </h2>
                <p className="text-sm text-[#607080]">
                  Update your producer information.
                </p>
              </div>

              <button
                type="button"
                onClick={cancelEditing}
                className="rounded-xl p-2 text-[#45586B] transition hover:bg-[#F5F1E5]"
                aria-label="Close edit profile"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            <div className="grid gap-5 p-5 sm:grid-cols-2 sm:p-7">
              <label className="sm:col-span-2">
                <span className="mb-2 block text-sm font-bold text-[#123C5C]">
                  Full Name
                </span>
                <input
                  value={draftProducer.name || ""}
                  onChange={(event) =>
                    updateField("name", event.target.value)
                  }
                  className="w-full rounded-xl border border-[#D6DDD6] bg-[#FBFCF9] px-4 py-3 text-sm text-[#123C5C] outline-none transition focus:border-[#159447] focus:ring-2 focus:ring-[#159447]/15"
                />
              </label>

              <label>
                <span className="mb-2 block text-sm font-bold text-[#123C5C]">
                  Email
                </span>
                <input
                  type="email"
                  value={draftProducer.email || ""}
                  onChange={(event) =>
                    updateField("email", event.target.value)
                  }
                  className="w-full rounded-xl border border-[#D6DDD6] bg-[#FBFCF9] px-4 py-3 text-sm text-[#123C5C] outline-none transition focus:border-[#159447] focus:ring-2 focus:ring-[#159447]/15"
                />
              </label>

              <label>
                <span className="mb-2 block text-sm font-bold text-[#123C5C]">
                  Contact
                </span>
                <input
                  value={draftProducer.contact_number || ""}
                  onChange={(event) =>
                    updateField("contact_number", event.target.value)
                  }
                  className="w-full rounded-xl border border-[#D6DDD6] bg-[#FBFCF9] px-4 py-3 text-sm text-[#123C5C] outline-none transition focus:border-[#159447] focus:ring-2 focus:ring-[#159447]/15"
                />
              </label>

              <label>
                <span className="mb-2 block text-sm font-bold text-[#123C5C]">
                  RSBSA Number
                </span>
                <input
                  value={draftProducer.rsbsa_number || ""}
                  onChange={(event) =>
                    updateField("rsbsa_number", event.target.value)
                  }
                  className="w-full rounded-xl border border-[#D6DDD6] bg-[#FBFCF9] px-4 py-3 text-sm text-[#123C5C] outline-none transition focus:border-[#159447] focus:ring-2 focus:ring-[#159447]/15"
                />
              </label>

              <label>
                <span className="mb-2 block text-sm font-bold text-[#123C5C]">
                  Lokasyon
                </span>
                <input
                  value={draftProducer.location || ""}
                  onChange={(event) =>
                    updateField("location", event.target.value)
                  }
                  className="w-full rounded-xl border border-[#D6DDD6] bg-[#FBFCF9] px-4 py-3 text-sm text-[#123C5C] outline-none transition focus:border-[#159447] focus:ring-2 focus:ring-[#159447]/15"
                />
              </label>

              <label>
                <span className="mb-2 block text-sm font-bold text-[#123C5C]">
                  Pangunahing Produkto
                </span>
                <input
                  value={draftProducer.primary_product_type || ""}
                  onChange={(event) =>
                    updateField("primary_product_type", event.target.value)
                  }
                  className="w-full rounded-xl border border-[#D6DDD6] bg-[#FBFCF9] px-4 py-3 text-sm text-[#123C5C] outline-none transition focus:border-[#159447] focus:ring-2 focus:ring-[#159447]/15"
                />
              </label>
            </div>

            <div className="flex flex-col-reverse gap-3 border-t border-[#E7E1D0] bg-[#FBFCF9] px-5 py-4 sm:flex-row sm:justify-end sm:px-7">
              <button
                type="button"
                onClick={cancelEditing}
                className="rounded-xl border border-[#D6DDD6] px-5 py-3 text-sm font-bold text-[#45586B] transition hover:bg-white"
              >
                Kanselahin
              </button>

              <button
                type="button"
                onClick={saveChanges}
                className="inline-flex items-center justify-center gap-2 rounded-xl bg-[#159447] px-5 py-3 text-sm font-bold text-white transition hover:bg-[#107638]"
              >
                <Save className="h-4 w-4" />
                I-save ang Changes
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
