---
name: HydroBento Precision Laundry System
colors:
  surface: '#f8f9fb'
  surface-dim: '#d8dadc'
  surface-bright: '#f8f9fb'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f2f4f6'
  surface-container: '#eceef0'
  surface-container-high: '#e6e8ea'
  surface-container-highest: '#e0e3e5'
  on-surface: '#191c1e'
  on-surface-variant: '#45474c'
  inverse-surface: '#2d3133'
  inverse-on-surface: '#eff1f3'
  outline: '#76777c'
  outline-variant: '#c6c6cc'
  surface-tint: '#585e6d'
  primary: '#000000'
  on-primary: '#ffffff'
  primary-container: '#161c28'
  on-primary-container: '#7e8493'
  inverse-primary: '#c1c6d7'
  secondary: '#1e667f'
  on-secondary: '#ffffff'
  secondary-container: '#a1e0fd'
  on-secondary-container: '#1c657e'
  tertiary: '#000000'
  on-tertiary: '#ffffff'
  tertiary-container: '#002113'
  on-tertiary-container: '#009668'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#dde2f4'
  primary-fixed-dim: '#c1c6d7'
  on-primary-fixed: '#161c28'
  on-primary-fixed-variant: '#414755'
  secondary-fixed: '#bde9ff'
  secondary-fixed-dim: '#90cfec'
  on-secondary-fixed: '#001f2a'
  on-secondary-fixed-variant: '#004d64'
  tertiary-fixed: '#47ffb8'
  tertiary-fixed-dim: '#00e29d'
  on-tertiary-fixed: '#002113'
  on-tertiary-fixed-variant: '#005236'
  background: '#f8f9fb'
  on-background: '#191c1e'
  surface-variant: '#e0e3e5'
typography:
  display-hero:
    fontFamily: Space Grotesk
    fontSize: 56px
    fontWeight: '700'
    lineHeight: 64px
    letterSpacing: -0.03em
  display-hero-mobile:
    fontFamily: Space Grotesk
    fontSize: 38px
    fontWeight: '700'
    lineHeight: 44px
    letterSpacing: -0.02em
  headline-lg:
    fontFamily: Space Grotesk
    fontSize: 32px
    fontWeight: '600'
    lineHeight: 40px
    letterSpacing: -0.02em
  headline-md:
    fontFamily: Space Grotesk
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 32px
    letterSpacing: -0.01em
  headline-sm:
    fontFamily: Space Grotesk
    fontSize: 18px
    fontWeight: '600'
    lineHeight: 24px
  body-lg:
    fontFamily: Hanken Grotesk
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  body-md:
    fontFamily: Hanken Grotesk
    fontSize: 14px
    fontWeight: '400'
    lineHeight: 20px
  body-sm:
    fontFamily: Hanken Grotesk
    fontSize: 12px
    fontWeight: '400'
    lineHeight: 16px
  label-caps:
    fontFamily: Space Grotesk
    fontSize: 11px
    fontWeight: '700'
    lineHeight: 14px
    letterSpacing: 0.08em
  metric-large:
    fontFamily: Space Grotesk
    fontSize: 44px
    fontWeight: '700'
    lineHeight: 48px
    letterSpacing: -0.03em
  metric-large-mobile:
    fontFamily: Space Grotesk
    fontSize: 32px
    fontWeight: '700'
    lineHeight: 36px
    letterSpacing: -0.02em
  metric-sm:
    fontFamily: Space Grotesk
    fontSize: 20px
    fontWeight: '600'
    lineHeight: 26px
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  gutter: 1rem
  margin: 1.25rem
  space-xs: 0.25rem
  space-sm: 0.5rem
  space-md: 1rem
  space-lg: 1.5rem
  space-xl: 2.25rem
---

## Brand & Style
This design system establishes a high-precision, tactile, and modern micro-dashboard experience for on-demand fabric care and garment management. Eschewing the conventional soft pastel motifs of household cleaning apps, this interface borrows from surgical telemetry, industrial product ergonomics, and cutting-edge productivity software.

The aesthetic philosophy centers around structured asymmetry and technical clarity:
- **Modular Bento Grid Architecture:** Content is compartmentalized into purposeful visual blocks, alternating between dense telemetry (weight metrics, cycle countdowns, wash telemetry) and broad interactive surfaces (booking slots, pickup addresses).
- **Tactile Crispness:** Fine-gauge outlines define perimeter boundaries, offset by deep, saturated voids and high-luminescence status accents.
- **Audience & Resonance:** Targeted at urban professionals, high-frequency laundromat patrons, and garment enthusiasts seeking complete visibility, schedule certainty, and zero ambiguity in status tracking. The visual environment evokes absolute hygiene, mechanical reliability, and effortless control.

## Colors
The palette balances deep architectural slates with high-frequency neon accents to provide immediate visual hierarchy within bento modular units.

- **Deep Navy Slate (Primary — `#080E1A`):** The grounding tone used for key cards, high-priority dashboard widgets, primary typographic elements, and full-bleed hero tiles.
- **Cobalt Deep Teal (Secondary — `#0D5C75`):** Serves as structural supporting color, section accents, active tab markers, and secondary metric containers.
- **Electric Mint (Tertiary — `#00F2A9`):** The primary signal indicator. Reserved strictly for real-time progress indicators, positive state confirmations, live cycle telemetry, and high-impact CTA focus rings.
- **Clean Slate (Neutral Base — `#F4F6F8`):** Canvas backdrop tone providing a crisp foundation against which bento borders and cards pop cleanly.
- **Sub-tier Neutrals:**
  - Border Crisp: `#E2E8F0` on light canvases; `#1E293B` on dark canvas tiles.
  - Surface Pure: `#FFFFFF` for default bento cards.
  - Muted Text: `#64748B` for operational subtitles, timestamps, and secondary specifications.

## Typography
Typography is split purposefully between structural technological clarity and ergonomic legibility:

- **Headlines & Metric Readouts (Space Grotesk):** Provides mathematical geometry, sharp aperture cuts, and tabular weight suited for digital timers, laundry weights (kg), pricing, and active cycle steps.
- **Editorial Body & Functional Descriptions (Hanken Grotesk):** Provides neutral, low-strain legibility across multi-line order summaries, driver instructions, and fabric care labels.
- **Micro-Data & Labels:** Always employ `label-caps` in uppercase format with wide tracking (`0.08em`) to mimic instrumentation readouts and hardware consoles.

## Layout & Spacing
The layout relies on a strict bento-grid modular matrix characterized by asymmetric spatial grouping:

- **Bento Framework:**
  - **Desktop (1024px+):** 12-column dynamic grid, 16px (`1rem`) gutters, with 4-row bento blocks. Cards span asymmetric column ratios such as `8:4`, `7:5`, or `3:3:6`.
  - **Tablet (768px – 1023px):** 6-column grid with `4:2` or `3:3` spans; high-density telemetry tiles compress horizontally.
  - **Mobile (< 768px):** 4-column compact grid, 12px outer canvas margins. Bento blocks collapse into an alternating `2:2` (half-width telemetry blocks) and `4` (full-width dominant cards) rhythm.
- **Internal Density:**
  - Internal card padding defaults to `space-lg` (`1.5rem`) on desktop and `space-md` (`1rem`) on compact phone displays to retain maximized content surface.
  - Metric groupings must maintain a dense `space-xs` gap between units (`kg`, `min`, `°C`) and their numerical values.

## Elevation & Depth
This design system abandons diffuse skeuomorphic drop shadows in favor of **Crisp Architectural Outlines and Solid Tonal Depth**:

- **Borders over Shadows:** Every modular bento unit is bounded by a hairline 1px solid border (`#E2E8F0` on white surfaces; `#1E293B` on deep slate surfaces).
- **High-Contrast Dark Canvas Islands:** Dominant dashboard widgets invert the palette entirely, using `#080E1A` as their background with electric mint text and metrics, providing instant primary elevation without 3D extrusion.
- **Offset Drop Accents (Micro-Tactile):** Critical interactive cards and floating action elements utilize a hard-edge offset shadow: `box-shadow: 3px 3px 0px #080E1A`, eliminating blur for a sharp industrial feel.
- **Overlays & Drawers:** Modal sheets and cycle drawer expansions employ a semi-opaque `#080E1A`/70 backdrop blur (`backdrop-filter: blur(8px)`).

## Shapes
The form language balances structural containment with smooth ergonomics:

- **Bento Enclosures:** All bento grid cards, dashboard blocks, and dominant modules use `rounded-xl` (`1.5rem` / `24px`), creating distinctive soft rectangular chambers.
- **Interactive Triggers:** Primary buttons, toggle pills, and filter selectors utilize full pill contours (`rounded-full`) to contrast against the rigid rectangular logic of the bento boxes.
- **Data Chips & Steppers:** Micro-badges, temperature toggles, and status markers adopt `0.5rem` (`8px`) corner radiuses for crisp precision.

## Components

### 1. Bento Dashboard Cards
- **Structure:** Encased in 1px solid hairline borders with `rounded-xl` geometry.
- **Primary Hero Card:** Inverted `#080E1A` background, containing large-scale cycle countdowns, active stage graphic (Washing, Rinsing, Drying, Ready), and an Electric Mint (`#00F2A9`) telemetry trace.
- **Metric Cards:** Light `#FFFFFF` surfaces with uppercase `label-caps` in `#64748B`, bold numeric values in Space Grotesk, and integrated status icons in top-right anchors.

### 2. Buttons & Action Bars
- **Primary Action:** Solid `#080E1A` pill with high-contrast `#FFFFFF` or `#00F2A9` typography, subtle 1px border. Hover transitions invoke a hard-edge `2px 2px 0px #00F2A9` offset shadow.
- **Telemetry Action (Quick Wash / Fast Schedule):** Solid `#00F2A9` pill with `#080E1A` bold labels.
- **Ghost/Outlined:** 1px `#E2E8F0` border, transparent background, text in Deep Slate.

### 3. Metric Chips & Badges
- **Status Chips:** Compact pill enclosures with a solid 6px status LED dot. Green/Electric Mint indicates "Spinning / Optimal", Amber indicates "Stain Treatment Required", and Slate indicates "Idle".
- **Weight/Care Tags:** Monospaced or tabular Space Grotesk numbers encapsulated in subtle slate borders (`#E2E8F0`) with solid `#F8FAFC` backgrounds.

### 4. Input Fields & Selectors
- **Input Chambers:** Modular boxes with inset labels. The input title lives inside the top border in `label-caps`.
- **Focus State:** 1.5px continuous outline in Electric Mint (`#00F2A9`) or Deep Slate (`#080E1A`) with zero blurry ring spreads.

### 5. Asymmetric Cycle Progress Trackers
- Segmented linear gauges replacing standard circular spinners. Each stage (Weigh, Wash, Rinse, Dry, Press) occupies an asymmetric segment proportionate to total duration, filled dynamically with Electric Mint against Deep Navy Slate.

### 6. Interactive Fabric Selectors
- Modular bento tiles presenting fabric categories (Cotton, Delicates, Wool, Heavy Bedding) with integrated weight capacity indicators, visual density badges, and toggle switches integrated into the card perimeter.