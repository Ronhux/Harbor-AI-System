import type { CSSProperties } from 'react';
import harvey from '../../../Images/harvey.png';
import huxel from '../../../Images/huxel.png';
import veronica from '../../../Images/beka.png';
import melody from '../../../Images/melody.png';

interface DevelopmentTeamMember {
  name: string;
  role: string;
  image: string;
  /**
   * Marks the Technical Adviser so the advisory role reads distinctly from
   * the three developer portraits.
   */
  isAdviser?: boolean;
}

/* Portrait photographs for the Development Team section.
   Each entry pairs one of the four individual photographs with the
   matching contributor name and role. */
const TEAM_MEMBERS: DevelopmentTeamMember[] = [
  {
    name: 'Harvey Deseo',
    role: 'Technical Adviser',
    image: harvey,
    isAdviser: true,
  },
  {
    name: 'Huxel Rohn L. Palmea',
    role: 'Team Developer / Team Leader',
    image: huxel,
  },
  {
    name: 'Veronica C. Cervantes',
    role: 'Front-End Developer',
    image: veronica,
  },
  {
    name: 'Melody O. Furagganan',
    role: 'Back-End Developer',
    image: melody,
  },
];

/**
 * Feathered oval mask applied to every portrait.
 *
 * The head and shoulders stay fully opaque while the lower part of the image
 * dissolves into the section background, so the subject reads as a floating
 * cutout rather than a framed rectangle. The ellipse also softens the left
 * and right edges.
 */
const PORTRAIT_FADE: CSSProperties = {
  maskImage:
    'radial-gradient(120% 88% at 50% 34%, #000 0%, #000 42%, rgba(0,0,0,0.28) 76%, rgba(0,0,0,0) 100%)',
  WebkitMaskImage:
    'radial-gradient(120% 88% at 50% 34%, #000 0%, #000 42%, rgba(0,0,0,0.28) 76%, rgba(0,0,0,0) 100%)',
  maskRepeat: 'no-repeat',
  WebkitMaskRepeat: 'no-repeat',
  maskSize: '100% 100%',
  WebkitMaskSize: '100% 100%',
};

export default function DevelopmentTeam() {
  return (
    <section
      id="team"
      aria-labelledby="team-heading"
      className="
        relative
        overflow-hidden
        scroll-mt-20
        bg-gradient-to-b
        from-[#FDF6E3]
        via-[#F7EBCF]
        to-[#EDF4E4]
        py-20
        sm:py-24
        md:scroll-mt-24
      "
    >
      {/* Atmospheric depth: soft golden-hour glow, mirroring the light
          direction in the reference landscape. */}
      <div
        aria-hidden="true"
        className="
          pointer-events-none
          absolute
          -top-32
          left-1/2
          h-72
          w-[42rem]
          -translate-x-1/2
          rounded-full
          bg-[#FBBE24]/25
          blur-[110px]
        "
      />

      {/* Layered terrain: distant ridges, mid-ground hills and terraced
          contours give the portraits the same depth layering as the
          reference image rather than sitting on a flat background. */}
      <div aria-hidden="true" className="pointer-events-none absolute inset-x-0 bottom-0 h-64 sm:h-72">
        <svg
          className="absolute inset-x-0 bottom-0 h-full w-full"
          viewBox="0 0 1440 320"
          preserveAspectRatio="none"
          aria-hidden="true"
        >
          <path
            d="M0,192 C180,140 320,214 512,178 C704,142 848,206 1032,170 C1196,138 1328,182 1440,154 L1440,320 L0,320 Z"
            fill="#D8E8C8"
            opacity="0.45"
          />
          <path
            d="M0,236 C160,196 336,258 520,222 C712,184 864,246 1056,212 C1224,182 1336,226 1440,200 L1440,320 L0,320 Z"
            fill="#BFDCA8"
            opacity="0.55"
          />
          <path
            d="M0,278 C200,246 380,296 576,264 C776,232 944,286 1144,256 C1280,234 1360,266 1440,248 L1440,320 L0,320 Z"
            fill="#9BCB84"
            opacity="0.5"
          />
        </svg>
      </div>

      <div className="relative container mx-auto px-4 sm:px-6 lg:px-8">
        <div className="mx-auto max-w-3xl text-center">
          <p
            className="
              text-[#3B7A22]
              font-bold
              uppercase
              tracking-[0.18em]
              text-sm
              mb-3
            "
          >
            Meet Our Team
          </p>

          <h2
            id="team-heading"
            className="
              font-display
              text-4xl
              sm:text-5xl
              md:text-6xl
              text-foreground
              mb-6
              section-heading
            "
          >
            OUR DEVELOPMENT TEAM
          </h2>

          <p className="text-lg text-muted-foreground leading-relaxed">
            The people who designed, built and continue to grow HarborAI
            for the agricultural community of Aparri,
            Cagayan.
          </p>
        </div>

        {/* Composed showcase: a rising, layered arrangement rather than a
            strict row of equal cards. The offsets echo the rolling ridges of
            the reference landscape and only apply from the large breakpoint. */}
        <ul className="mt-14 grid grid-cols-2 gap-x-5 gap-y-10 sm:gap-x-8 lg:grid-cols-4 lg:gap-x-10">
          {TEAM_MEMBERS.map((member, index) => (
            <li
              key={member.name}
              className={`
                group
                relative
                flex
                flex-col
                items-center
                text-center
                transition-transform
                duration-500
                ease-out
                hover:-translate-y-2
                focus-within:-translate-y-2
                motion-reduce:transition-none
                motion-reduce:hover:translate-y-0
                motion-reduce:focus-within:translate-y-0
                lg:items-center
                ${
                  index === 0
                    ? 'lg:mt-0'
                    : index === 1
                      ? 'lg:mt-20'
                      : index === 2
                        ? 'lg:mt-10'
                        : 'lg:mt-24'
                }
              `}
            >
              {/* Atmospheric halo behind each portrait. The adviser carries a
                  slightly warmer, stronger glow as a quiet emphasis. */}
              <div
                aria-hidden="true"
                className={`
                  pointer-events-none
                  absolute
                  left-1/2
                  top-4
                  h-32
                  w-32
                  -translate-x-1/2
                  rounded-full
                  blur-3xl
                  transition-opacity
                  duration-500
                  group-hover:opacity-90
                  motion-reduce:transition-none
                  sm:h-40
                  sm:w-40
                  ${member.isAdviser
                    ? 'bg-[#FBBE24]/40'
                    : 'bg-[#FBBE24]/25'}
                `}
              />

              {/* Quiet adviser marker â€” a soft tinted caption rather than a
                  loud filled badge. Non-adviser entries reserve the same
                  height so the composition stays row-aligned. */}
              <span
                aria-hidden={member.isAdviser ? undefined : 'true'}
                className={`
                  relative
                  z-10
                  mb-1
                  inline-flex
                  items-center
                  gap-1.5
                  rounded-full
                  px-2.5
                  py-0.5
                  text-[0.6rem]
                  font-bold
                  uppercase
                  tracking-[0.16em]
                  sm:text-[0.65rem]
                  ${member.isAdviser
                    ? 'bg-[#FBBE24]/25 text-[#8A5A00]'
                    : 'text-transparent'}
                `}
              >
                {member.isAdviser ? 'Technical Adviser' : 'Adviser'}
              </span>

              {/* Cutout portrait â€” no card, no white frame, no hard border.
                  The feathered mask dissolves the lower part of the image into
                  the section background, and a soft drop shadow keeps the
                  subject feeling placed on the scene rather than boxed in. */}
              <img
                src={member.image}
                alt={`Portrait of ${member.name}, ${member.role}`}
                loading="lazy"
                decoding="async"
                style={PORTRAIT_FADE}
                className="
                  relative
                  aspect-[4/5]
                  w-full
                  object-cover
                  object-top
                  drop-shadow-[0_12px_18px_rgba(18,60,92,0.20)]
                  transition-transform
                  duration-500
                  ease-out
                  group-hover:scale-[1.03]
                  motion-reduce:transition-none
                  motion-reduce:group-hover:scale-100
                  lg:w-[94%]
                "
              />

              <h3 className="relative z-10 mt-1 font-display text-base leading-tight tracking-tight text-foreground min-[430px]:text-lg sm:text-2xl lg:text-xl">
                {member.name}
              </h3>

              <p className="relative z-10 mt-0.5 text-[0.65rem] font-bold uppercase leading-snug tracking-wide text-[#3B7A22] min-[430px]:text-xs sm:text-sm">
                {member.role}
              </p>
            </li>
          ))}
        </ul>
      </div>
    </section>
  );
}
