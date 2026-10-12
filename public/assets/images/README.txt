Infinity Blog — Visual Assets
=============================

These individual PNGs are separated from the generated 3×3 reference-style
visual sheet. They follow the Infinity Blog mockup's pastel blue, lavender,
peach, and warm sunrise look, with soft depth-of-field and clean workspace
photography.

Files
-----
hero-workspace-background.png
  Use on the public homepage, behind the main hero area.

login-gradient-background.png
  Use behind the sign-in card; keep the form itself readable.

dashboard-plant-window-background.png
  Use as a soft dashboard page background.

technology-article-laptop.png
  Technology category / web development article thumbnail.

lifestyle-forest-road.png
  Lifestyle and productivity article thumbnail.

education-study-notes.png
  Education / study article thumbnail.

coding-coffee-workspace.png
  Alternate technology or productivity article thumbnail.

nature-trail-scenery.png
  Nature, lifestyle, or travel article thumbnail.

floating-blog-documents.png
  Decorative hero illustration; use with object-fit: contain.

asset-sheet-preview.png
  Preview of the 3×3 sheet.

Suggested CSS
-------------
.page-background {
  background-size: cover;
  background-position: center;
  background-repeat: no-repeat;
}

.glass-panel {
  background: rgba(255, 255, 255, 0.58);
  border: 1px solid rgba(255, 255, 255, 0.7);
  backdrop-filter: blur(18px);
  border-radius: 18px;
}

.article-thumbnail {
  width: 100%;
  aspect-ratio: 16 / 9;
  object-fit: cover;
  border-radius: 12px;
}

Note: Use the real image files as visual assets, not as screenshots of entire UI
screens. Use CSS for glass cards, borders, blur, layout, and text so pages remain
responsive and editable.
