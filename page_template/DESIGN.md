---
name: StockEase Design System
colors:
  surface: '#f8f9ff'
  surface-dim: '#cbdbf5'
  surface-bright: '#f8f9ff'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#eff4ff'
  surface-container: '#e5eeff'
  surface-container-high: '#dce9ff'
  surface-container-highest: '#d3e4fe'
  on-surface: '#0b1c30'
  on-surface-variant: '#3c4a42'
  inverse-surface: '#213145'
  inverse-on-surface: '#eaf1ff'
  outline: '#6c7a71'
  outline-variant: '#bbcabf'
  surface-tint: '#006c49'
  primary: '#006c49'
  on-primary: '#ffffff'
  primary-container: '#10b981'
  on-primary-container: '#00422b'
  inverse-primary: '#4edea3'
  secondary: '#2b6954'
  on-secondary: '#ffffff'
  secondary-container: '#adedd3'
  on-secondary-container: '#306d58'
  tertiary: '#55615f'
  on-tertiary: '#ffffff'
  tertiary-container: '#98a5a3'
  on-tertiary-container: '#2f3b39'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#6ffbbe'
  primary-fixed-dim: '#4edea3'
  on-primary-fixed: '#002113'
  on-primary-fixed-variant: '#005236'
  secondary-fixed: '#b0f0d6'
  secondary-fixed-dim: '#95d3ba'
  on-secondary-fixed: '#002117'
  on-secondary-fixed-variant: '#0b513d'
  tertiary-fixed: '#d8e5e2'
  tertiary-fixed-dim: '#bcc9c6'
  on-tertiary-fixed: '#121e1c'
  on-tertiary-fixed-variant: '#3d4947'
  background: '#f8f9ff'
  on-background: '#0b1c30'
  surface-variant: '#d3e4fe'
typography:
  display-lg:
    fontFamily: Inter
    fontSize: 48px
    fontWeight: '700'
    lineHeight: 56px
    letterSpacing: -0.02em
  headline-lg:
    fontFamily: Inter
    fontSize: 32px
    fontWeight: '600'
    lineHeight: 40px
    letterSpacing: -0.01em
  headline-md:
    fontFamily: Inter
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 32px
  body-lg:
    fontFamily: Inter
    fontSize: 18px
    fontWeight: '400'
    lineHeight: 28px
  body-md:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  label-md:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '500'
    lineHeight: 20px
  label-sm:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '600'
    lineHeight: 16px
  headline-lg-mobile:
    fontFamily: Inter
    fontSize: 28px
    fontWeight: '600'
    lineHeight: 36px
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  unit: 8px
  gutter: 24px
  margin-desktop: 40px
  margin-mobile: 16px
  container-max: 1440px
---

## Brand & Style
The design system focuses on precision, reliability, and modern efficiency. It targets retail managers and business owners who require a high-density information environment that remains breathable and easy to navigate.

The visual style is **Corporate Modern with subtle Glassmorphic influences**. It utilizes a "Clean SaaS" aesthetic characterized by high-quality typography, generous white space, and a professional emerald green palette. The interface leverages soft gradients and semi-transparent layers to establish a sophisticated hierarchy, moving away from flat design toward a more tactile, depth-oriented experience.

## Colors
The palette is centered around **Emerald Green**, chosen for its associations with growth, stability, and financial health.

- **Primary Emerald (#10B981):** Used for primary actions, success states, and brand accents.
- **Deep Forest (#064E3B):** Used for high-contrast text and dark-mode headers to maintain professional weight.
- **Soft Mint (#F0FDFA):** A background tint used for subtle section grouping and hover states.
- **Slate Neutral (#64748B):** Used for secondary text, borders, and UI chrome to provide a balanced, low-strain reading environment.

The system supports a full **Dark Mode** transition where surfaces move to a deep Navy-Slate (#0F172A) and primary greens shift toward higher vibrancy (#34D399) to ensure AAA accessibility.

## Typography
This design system uses **Inter** exclusively to ensure maximum legibility in data-heavy ERP environments. The type scale is built on a 4px baseline grid.

For dashboard views, use `label-md` for table headers and `body-md` for cell content. `display-lg` is reserved for marketing landing pages and empty-state hero sections. On mobile devices, headlines should automatically scale down to their `-mobile` variants to prevent excessive line wrapping.

## Layout & Spacing
The layout follows a **12-column Fluid Grid** for the main dashboard content, while maintaining a fixed-width sidebar (280px). 

- **Desktop:** 12 columns, 24px gutters, 40px outer margins.
- **Tablet:** 8 columns, 16px gutters, 24px outer margins.
- **Mobile:** 4 columns, 16px gutters, 16px outer margins.

Spacing follows an 8px rhythmic scale. Use smaller 4px increments for internal component spacing (e.g., icon-to-label distance) and larger 16px+ increments for section grouping.

## Elevation & Depth
Depth is created using a combination of **Tonal Layers** and **Soft Ambient Shadows**. 

1. **Base Layer:** Background color (Light: #F8FAFC, Dark: #0F172A).
2. **Surface Layer:** White (or #1E293B in dark) cards with a 1px border (#E2E8F0) and a subtle blur shadow (0 4px 6px -1px rgba(0,0,0,0.1)).
3. **Overlay Layer:** Modals and dropdowns utilize a stronger shadow (0 20px 25px -5px rgba(0,0,0,0.1)) and a background-blur backdrop (12px) to simulate glassmorphism.

Avoid heavy black shadows; instead, use shadows tinted with the primary emerald or deep slate to keep the UI looking integrated and clean.

## Shapes
The shape language is "Rounded," striking a balance between approachable and professional. 

- **Small elements (Checkboxes, Tags):** Use 0.5rem (8px).
- **Standard components (Buttons, Inputs, Cards):** Use 1rem (16px).
- **Large Containers (Modals, Hero Cards):** Use 1.5rem (24px).

Buttons should never be fully pill-shaped; they should maintain a structured "squircle" look to align with the grid-based layout of the ERP software.

## Components

### Buttons
Primary buttons use a subtle vertical gradient from Emerald-500 to Emerald-600 with white text. Secondary buttons are outlined with a 1.5px border in Slate-200.

### Input Fields
Inputs use a 16px corner radius. On focus, the border shifts to Emerald-500 with a 3px soft glow (20% opacity emerald) to provide clear focus state feedback.

### Data Cards
Cards are the primary container for POS data. They feature a 1px border and no shadow by default, gaining a soft shadow only on hover to indicate interactivity.

### Chips & Status Indicators
Status chips (e.g., "In Stock," "Low Stock") use high-saturation backgrounds at 10% opacity with 100% opacity text of the same color for high legibility without visual clutter.

### Navigation Sidebar
The sidebar should use a slightly darker neutral background than the main canvas to provide a clear anchor for the user. Active states are indicated by a 4px vertical "pill" indicator on the left edge of the menu item.