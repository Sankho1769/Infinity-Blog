/**
 * Infinity Blog - Standalone Offline File Preview Engine
 * 
 * Activates ONLY when opened directly via the file protocol (file:///).
 * Browsers block ES modules (type="module") and HTTP fetch on file:///.
 * This script provides full interactive client-side rendering for buttons,
 * categories, articles, search, and navigation so the project is 100% visible
 * and functional even without a running local server.
 * 
 * When served over http:// or https://, this script remains completely dormant.
 */

(function () {
    'use strict';

    if (window.location.protocol !== 'file:') {
        return; // Dormant on real web servers (Apache, Nginx, PHP CLI)
    }

    console.info('[Infinity Blog] Running in Standalone Offline Preview Mode (file:/// protocol detected).');

    // Sample Dataset for Offline Preview
    var SAMPLE_CATEGORIES = [
        { id: 1, name: 'Web Development', slug: 'web-development', description: 'Modern front-end and full-stack web engineering practices.', post_count: 5 },
        { id: 2, name: 'Software Architecture', slug: 'software-architecture', description: 'Design patterns, microservices, and system scalability.', post_count: 3 },
        { id: 3, name: 'AI & Machine Learning', slug: 'ai-machine-learning', description: 'Artificial intelligence, neural networks, and prompt engineering.', post_count: 4 },
        { id: 4, name: 'DevOps & Cloud', slug: 'devops-cloud', description: 'Continuous integration, Docker containers, and cloud deployments.', post_count: 2 },
        { id: 5, name: 'Cybersecurity', slug: 'cybersecurity', description: 'Application hardening, secure coding, and threat mitigation.', post_count: 3 }
    ];

    var SAMPLE_AUTHORS = [
        {
            id: 1,
            display_name: 'Sounak Sikdar',
            role: 'admin',
            bio: 'Full-stack software architect, open-source enthusiast, and security researcher.',
            created_at: '2026-01-15T10:00:00Z',
            total_posts: 8,
            published_posts: 6,
            total_views: 18450
        },
        {
            id: 2,
            display_name: 'Jane Doe',
            role: 'author',
            bio: 'Specialist in distributed systems, modern PHP 8.2 architectures, and cloud infrastructure.',
            created_at: '2026-02-10T12:00:00Z',
            total_posts: 5,
            published_posts: 4,
            total_views: 9230
        },
        {
            id: 3,
            display_name: 'John Smith',
            role: 'author',
            bio: 'UI/UX enthusiast writing about clean typography, responsive design, and vanilla JavaScript.',
            created_at: '2026-03-01T08:30:00Z',
            total_posts: 4,
            published_posts: 3,
            total_views: 6710
        }
    ];

    var SAMPLE_ARTICLES = [
        {
            id: 1,
            title: 'Getting Started with Web Development in 2026',
            content: 'Web development in 2026 blends modern semantic HTML5, clean modular CSS, and lightning-fast vanilla ES modules. By leveraging browser-native APIs and clean REST architectural boundaries, applications achieve unmatched reliability without unnecessary heavy framework dependencies.\n\n### Key Principles\n1. **Semantic Structure**: Use standard elements for optimal accessibility and search indexing.\n2. **Defensive Programming**: Validate all user input both client-side and server-side.\n3. **Performance First**: Minimize render-blocking dependencies for instant loading.',
            summary: 'An exploration of modern web development in 2026 emphasizing semantic HTML5, clean CSS, and lightweight architectures.',
            summary_status: 'ready',
            featured_image: '',
            tags: 'web, javascript, modern, html5',
            views: 1420,
            status: 'published',
            published_at: '2026-10-18T14:30:00Z',
            category_id: 1,
            category_name: 'Web Development',
            author_id: 1,
            author_name: 'Sounak Sikdar'
        },
        {
            id: 2,
            title: 'Modern PHP 8.2 & REST API Architectural Standards',
            content: 'PHP 8.2 introduces readonly classes, true types, and enhanced type safety that transform backend engineering. When building REST APIs under `/api/v1/`, strict JSON serialization, centralized routing, and prepared SQL statements deliver enterprise-grade performance and robust security.\n\n### Architectural Highlights\n- Strict type declarations (`declare(strict_types=1)`).\n- Atomic PDO transaction isolation.\n- Comprehensive audit logging on state mutations.',
            summary: 'Comprehensive guide to modern PHP 8.2 REST API design with strict typing and defensive programming.',
            summary_status: 'ready',
            featured_image: '',
            tags: 'php, rest-api, backend, architecture',
            views: 2890,
            status: 'published',
            published_at: '2026-10-17T09:15:00Z',
            category_id: 2,
            category_name: 'Software Architecture',
            author_id: 2,
            author_name: 'Jane Doe'
        },
        {
            id: 3,
            title: 'Defensive Security: Mitigating OWASP Top 10 in Content Management',
            content: 'Content management systems require multilayered security controls. Key defenses include strict session regeneration, anti-CSRF tokens on all mutating operations, parameterized SQL queries, rate limiting on authentication routes, and defensive sanitization of user-submitted content.',
            summary: 'Essential application security practices to defend CMS platforms against injection, XSS, and CSRF.',
            summary_status: 'ready',
            featured_image: '',
            tags: 'security, owasp, php, defensive',
            views: 3410,
            status: 'published',
            published_at: '2026-10-16T16:45:00Z',
            category_id: 5,
            category_name: 'Cybersecurity',
            author_id: 1,
            author_name: 'Sounak Sikdar'
        },
        {
            id: 4,
            title: 'Artificial Intelligence & Article Summarization Pipelines',
            content: 'Automated summarization empowers readers to quickly extract key insights. By implementing an asynchronous summarization engine, articles are processed reliably while maintaining canonical content integrity in relational databases.',
            summary: 'How AI summarization pipelines extract key article insights while maintaining database integrity.',
            summary_status: 'ready',
            featured_image: '',
            tags: 'ai, summarization, machine-learning',
            views: 1820,
            status: 'published',
            published_at: '2026-10-15T11:20:00Z',
            category_id: 3,
            category_name: 'AI & Machine Learning',
            author_id: 2,
            author_name: 'Jane Doe'
        },
        {
            id: 5,
            title: 'Containerization & Continuous Deployment with Docker',
            content: 'Deploying full-stack PHP and MySQL applications inside isolated containers ensures environment parity across development, staging, and production. Learn how to configure multi-stage Docker builds and secure networking.',
            summary: 'Best practices for containerizing full-stack PHP applications with Docker and automated pipelines.',
            summary_status: 'ready',
            featured_image: '',
            tags: 'devops, docker, containers, cloud',
            views: 2150,
            status: 'published',
            published_at: '2026-10-14T08:00:00Z',
            category_id: 4,
            category_name: 'DevOps & Cloud',
            author_id: 3,
            author_name: 'John Smith'
        }
    ];

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function renderBanner() {
        var existing = document.getElementById('offline-preview-banner');
        if (existing) return;

        var banner = document.createElement('div');
        banner.id = 'offline-preview-banner';
        banner.style.cssText = 'background:linear-gradient(90deg,#1e3a8a,#2563eb);color:#ffffff;padding:10px 16px;font-family:system-ui,-apple-system,sans-serif;font-size:13px;line-height:1.4;box-shadow:0 2px 8px rgba(0,0,0,0.2);position:sticky;top:0;z-index:99999;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;border-bottom:1px solid #3b82f6;';
        banner.innerHTML = '<div>' +
            '<strong>⚡ Offline File Preview Mode:</strong> All buttons, category filters, and articles are interactive. ' +
            'For full database saving & user accounts, run <code>run-infinity-blog.bat</code> or place in XAMPP <code>htdocs</code>.' +
            '</div>' +
            '<div style="font-size:12px;opacity:0.9;">Double-click <b>run-infinity-blog.bat</b> to start full server</div>';

        document.body.insertBefore(banner, document.body.firstChild);
    }

    function initHeader() {
        var navMenu = document.querySelector('.nav-menu');
        var headerActions = document.querySelector('.header-actions');
        var rawFile = window.location.pathname.split('/').pop().toLowerCase();
        var currentFile = rawFile.split('?')[0].split('#')[0] || 'index.html';

        if (navMenu) {
            navMenu.innerHTML = '' +
                '<li><a href="index.html" class="nav-link ' + (currentFile === 'index.html' ? 'active' : '') + '">Home</a></li>' +
                '<li><a href="categories.html" class="nav-link ' + (currentFile === 'categories.html' ? 'active' : '') + '">Categories</a></li>' +
                '<li><a href="authors.html" class="nav-link ' + (currentFile === 'authors.html' ? 'active' : '') + '">Authors</a></li>' +
                '<li><a href="dashboard.html" class="nav-link ' + (currentFile === 'dashboard.html' ? 'active' : '') + '">Dashboard</a></li>' +
                '<li><a href="posts.html" class="nav-link ' + (currentFile === 'posts.html' ? 'active' : '') + '">Posts</a></li>' +
                '<li><a href="create-post.html" class="nav-link ' + (currentFile === 'create-post.html' ? 'active' : '') + '">Create Post</a></li>' +
                '<li><a href="admin.html" class="nav-link ' + (currentFile === 'admin.html' ? 'active' : '') + '">Admin</a></li>';
        }

        if (headerActions) {
            headerActions.innerHTML = '' +
                '<a href="signup.html" class="btn btn-secondary btn-sm" style="margin-right:6px;">Sign Up</a>' +
                '<a href="login.html" class="btn btn-primary btn-sm">Sign In</a>';
        }
    }

    function initIndexPage() {
        var chipsContainer = document.getElementById('category-chips-container');
        var loadingEl = document.getElementById('articles-loading');
        var gridEl = document.getElementById('articles-grid');
        var emptyEl = document.getElementById('articles-empty');
        var countLabel = document.getElementById('articles-count-label');
        var searchInput = document.getElementById('search-input');
        var searchBtn = document.getElementById('search-btn');
        var resetBtn = document.getElementById('reset-btn');

        if (!chipsContainer || !gridEl) return;

        var activeCategory = null;
        var activeSearch = '';

        function renderCategories() {
            chipsContainer.innerHTML = '';
            var allBtn = document.createElement('button');
            allBtn.type = 'button';
            allBtn.className = 'category-chip ' + (activeCategory === null ? 'active' : '');
            allBtn.textContent = 'All Topics';
            allBtn.onclick = function () {
                activeCategory = null;
                renderCategories();
                renderArticles();
            };
            chipsContainer.appendChild(allBtn);

            SAMPLE_CATEGORIES.forEach(function (cat) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'category-chip ' + (activeCategory === cat.id ? 'active' : '');
                btn.textContent = cat.name;
                btn.onclick = function () {
                    activeCategory = cat.id;
                    renderCategories();
                    renderArticles();
                };
                chipsContainer.appendChild(btn);
            });
        }

        function renderArticles() {
            var filtered = SAMPLE_ARTICLES.filter(function (a) {
                var matchCat = activeCategory === null || a.category_id === activeCategory;
                var matchSearch = !activeSearch ||
                    a.title.toLowerCase().indexOf(activeSearch.toLowerCase()) !== -1 ||
                    (a.tags && a.tags.toLowerCase().indexOf(activeSearch.toLowerCase()) !== -1);
                return matchCat && matchSearch;
            });

            if (loadingEl) loadingEl.style.display = 'none';

            if (filtered.length === 0) {
                gridEl.style.display = 'none';
                if (emptyEl) emptyEl.style.display = 'block';
                if (countLabel) countLabel.textContent = '0 articles';
                return;
            }

            if (emptyEl) emptyEl.style.display = 'none';
            gridEl.style.display = 'grid';
            gridEl.innerHTML = '';

            if (countLabel) {
                countLabel.textContent = filtered.length + ' published article' + (filtered.length === 1 ? '' : 's');
            }

            filtered.forEach(function (post) {
                var card = document.createElement('article');
                card.className = 'article-card';

                var dateStr = new Date(post.published_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                var tagsHtml = '';
                if (post.tags) {
                    tagsHtml = '<div class="article-tags-list">' +
                        post.tags.split(',').map(function (t) {
                            return '<span class="tag-badge">#' + escapeHtml(t.trim()) + '</span>';
                        }).join('') +
                        '</div>';
                }

                card.innerHTML = '' +
                    '<div class="article-card-content">' +
                    '  <div class="article-meta-row">' +
                    '    <span class="badge badge-category">' + escapeHtml(post.category_name) + '</span>' +
                    '    <span class="article-date">' + escapeHtml(dateStr) + '</span>' +
                    '    <span class="article-views" title="Total Views">👁️ ' + Number(post.views).toLocaleString() + '</span>' +
                    '  </div>' +
                    '  <h2 class="article-title">' +
                    '    <a href="post.html?id=' + post.id + '">' + escapeHtml(post.title) + '</a>' +
                    '  </h2>' +
                    '  <p class="article-excerpt">' + escapeHtml(post.summary) + '</p>' +
                    tagsHtml +
                    '  <div class="article-footer-row">' +
                    '    <div class="article-author-info">' +
                    '      <div class="author-avatar">' + (post.author_name ? post.author_name.charAt(0) : 'A') + '</div>' +
                    '      <a href="author.html?id=' + post.author_id + '" class="author-name-link">' + escapeHtml(post.author_name) + '</a>' +
                    '    </div>' +
                    '    <a href="post.html?id=' + post.id + '" class="btn btn-primary btn-sm">Read More &rarr;</a>' +
                    '  </div>' +
                    '</div>';

                gridEl.appendChild(card);
            });
        }

        if (searchBtn && searchInput) {
            searchBtn.onclick = function () {
                activeSearch = searchInput.value.trim();
                renderArticles();
            };
            searchInput.onkeydown = function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    activeSearch = searchInput.value.trim();
                    renderArticles();
                }
            };
        }

        if (resetBtn) {
            resetBtn.onclick = function () {
                if (searchInput) searchInput.value = '';
                activeSearch = '';
                activeCategory = null;
                renderCategories();
                renderArticles();
            };
        }

        renderCategories();
        renderArticles();
    }

    function initPostPage() {
        var loadingEl = document.getElementById('post-loading');
        var articleEl = document.getElementById('post-article');
        if (!articleEl) return;

        var urlParams = new URLSearchParams(window.location.search);
        var postId = parseInt(urlParams.get('id'), 10) || 1;
        var post = SAMPLE_ARTICLES.find(function (a) { return a.id === postId; }) || SAMPLE_ARTICLES[0];

        if (loadingEl) loadingEl.style.display = 'none';
        articleEl.style.display = 'block';

        var titleEl = document.getElementById('post-title');
        var catEl = document.getElementById('post-category');
        var dateEl = document.getElementById('post-date');
        var authorEl = document.getElementById('post-author');
        var contentEl = document.getElementById('post-content');
        var viewsEl = document.getElementById('post-views-count');
        var tagsContainer = document.getElementById('post-tags-container');
        var summaryBox = document.getElementById('ai-summary-box');
        var summaryText = document.getElementById('ai-summary-text');

        if (titleEl) titleEl.textContent = post.title;
        if (catEl) catEl.textContent = post.category_name;
        if (dateEl) dateEl.textContent = new Date(post.published_at).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
        if (authorEl) {
            authorEl.textContent = post.author_name;
            authorEl.href = 'author.html?id=' + post.author_id;
        }
        if (viewsEl) viewsEl.textContent = Number(post.views + 1).toLocaleString() + ' views';

        if (contentEl) {
            contentEl.innerHTML = post.content
                .split('\n\n')
                .map(function (p) {
                    if (p.indexOf('### ') === 0) return '<h3>' + escapeHtml(p.replace('### ', '')) + '</h3>';
                    return '<p>' + escapeHtml(p) + '</p>';
                })
                .join('');
        }

        if (summaryBox && summaryText && post.summary) {
            summaryText.textContent = post.summary;
            summaryBox.style.display = 'block';
        }

        if (tagsContainer && post.tags) {
            tagsContainer.innerHTML = post.tags.split(',').map(function (t) {
                return '<span class="tag-badge">#' + escapeHtml(t.trim()) + '</span>';
            }).join(' ');
        }
    }

    function initCategoriesPage() {
        var loadingEl = document.getElementById('cat-loading');
        var cardEl = document.getElementById('cat-card');
        var tbody = document.getElementById('cat-tbody');

        if (!tbody) return;

        if (loadingEl) loadingEl.style.display = 'none';
        if (cardEl) cardEl.style.display = 'block';

        tbody.innerHTML = '';
        SAMPLE_CATEGORIES.forEach(function (c, idx) {
            var tr = document.createElement('tr');
            tr.innerHTML = '' +
                '<td>' + (idx + 1) + '</td>' +
                '<td><a href="index.html?category_id=' + c.id + '" style="font-weight:600;color:inherit;text-decoration:none;">' + escapeHtml(c.name) + '</a></td>' +
                '<td><code>' + escapeHtml(c.slug) + '</code></td>' +
                '<td style="color:var(--color-text-muted);font-size:0.875rem;">' + escapeHtml(c.description) + '</td>' +
                '<td><a href="index.html?category_id=' + c.id + '" class="badge badge-category" style="text-decoration:none;">' + c.post_count + ' posts</a></td>';
            tbody.appendChild(tr);
        });
    }

    function initAuthorsPage() {
        var loadingEl = document.getElementById('authors-loading');
        var gridEl = document.getElementById('authors-grid');

        if (!gridEl) return;
        if (loadingEl) loadingEl.style.display = 'none';
        gridEl.style.display = 'grid';

        gridEl.innerHTML = '';
        SAMPLE_AUTHORS.forEach(function (author) {
            var card = document.createElement('div');
            card.className = 'author-card';
            card.innerHTML = '' +
                '<div style="width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,var(--color-primary),#4f46e5);color:#fff;font-size:1.8rem;font-weight:700;display:flex;align-items:center;justify-content:center;margin-bottom:var(--space-4);box-shadow:var(--shadow-sm);">' +
                escapeHtml(author.display_name.charAt(0)) +
                '</div>' +
                '<h3 style="font-size:1.25rem;font-weight:700;margin-bottom:var(--space-1);">' +
                '  <a href="author.html?id=' + author.id + '" style="color:inherit;text-decoration:none;">' + escapeHtml(author.display_name) + '</a>' +
                '</h3>' +
                '<span class="badge" style="margin-bottom:var(--space-3);text-transform:capitalize;">' + escapeHtml(author.role) + '</span>' +
                '<p style="color:var(--color-text-muted);font-size:0.875rem;line-height:1.5;margin-bottom:var(--space-5);flex:1;">' + escapeHtml(author.bio) + '</p>' +
                '<div style="display:flex;justify-content:space-around;width:100%;border-top:1px solid var(--color-border);padding-top:var(--space-4);margin-bottom:var(--space-4);font-size:0.8rem;color:var(--color-text-muted);">' +
                '  <div><strong style="display:block;font-size:1.1rem;color:var(--color-text);">' + author.published_posts + '</strong><span>Articles</span></div>' +
                '  <div><strong style="display:block;font-size:1.1rem;color:var(--color-primary);">' + Number(author.total_views).toLocaleString() + '</strong><span>Views</span></div>' +
                '</div>' +
                '<a href="author.html?id=' + author.id + '" class="btn btn-outline btn-sm" style="width:100%;">View Profile &amp; Articles &rarr;</a>';
            gridEl.appendChild(card);
        });
    }

    function initAuthorDetailPage() {
        var loadingEl = document.getElementById('author-loading');
        var heroContainer = document.getElementById('author-hero-container');
        if (!heroContainer) return;

        var urlParams = new URLSearchParams(window.location.search);
        var authorId = parseInt(urlParams.get('id'), 10) || 1;
        var author = SAMPLE_AUTHORS.find(function (a) { return a.id === authorId; }) || SAMPLE_AUTHORS[0];

        if (loadingEl) loadingEl.style.display = 'none';
        heroContainer.style.display = 'block';

        var nameEl = document.getElementById('author-name');
        var bioEl = document.getElementById('author-bio');
        var roleEl = document.getElementById('author-role-badge');
        var avatarEl = document.getElementById('author-avatar-badge');
        var pubEl = document.getElementById('stat-published');
        var totEl = document.getElementById('stat-total');
        var viewEl = document.getElementById('stat-views');
        var postsGrid = document.getElementById('author-posts-grid');
        var countBadge = document.getElementById('author-articles-count');

        if (nameEl) nameEl.textContent = author.display_name;
        if (bioEl) bioEl.textContent = author.bio;
        if (roleEl) roleEl.textContent = author.role.toUpperCase();
        if (avatarEl) avatarEl.textContent = author.display_name.charAt(0);
        if (pubEl) pubEl.textContent = author.published_posts;
        if (totEl) totEl.textContent = author.total_posts;
        if (viewEl) viewEl.textContent = Number(author.total_views).toLocaleString();

        var authorArticles = SAMPLE_ARTICLES.filter(function (a) { return a.author_id === author.id; });
        if (countBadge) countBadge.textContent = authorArticles.length + ' articles';

        if (postsGrid) {
            postsGrid.innerHTML = '';
            authorArticles.forEach(function (post) {
                var card = document.createElement('article');
                card.className = 'post-card';
                card.innerHTML = '' +
                    '<div class="post-card-content">' +
                    '  <div class="post-card-meta">' +
                    '    <span class="badge badge-category">' + escapeHtml(post.category_name) + '</span>' +
                    '    <span class="post-views">👁️ ' + Number(post.views).toLocaleString() + '</span>' +
                    '  </div>' +
                    '  <h3 class="post-card-title"><a href="post.html?id=' + post.id + '">' + escapeHtml(post.title) + '</a></h3>' +
                    '  <p class="post-card-excerpt">' + escapeHtml(post.summary) + '</p>' +
                    '  <div class="post-card-footer"><a href="post.html?id=' + post.id + '" class="btn btn-outline btn-sm">Read Article &rarr;</a></div>' +
                    '</div>';
                postsGrid.appendChild(card);
            });
        }
    }

    function initPostsPage() {
        var loadingEl = document.getElementById('posts-loading');
        var cardEl = document.getElementById('posts-card');
        var tbody = document.getElementById('posts-tbody');
        if (!tbody) return;

        if (loadingEl) loadingEl.style.display = 'none';
        if (cardEl) cardEl.style.display = 'block';

        tbody.innerHTML = '';
        SAMPLE_ARTICLES.forEach(function (p) {
            var tr = document.createElement('tr');
            tr.innerHTML = '' +
                '<td><input type="checkbox"></td>' +
                '<td><strong>' + escapeHtml(p.title) + '</strong></td>' +
                '<td><span class="badge badge-category">' + escapeHtml(p.category_name) + '</span></td>' +
                '<td><span class="badge badge-published">Published</span></td>' +
                '<td><span class="badge">👁️ ' + Number(p.views).toLocaleString() + '</span></td>' +
                '<td style="color:var(--color-text-muted);font-size:0.85rem;">' + new Date(p.published_at).toLocaleDateString() + '</td>' +
                '<td style="text-align:right;">' +
                '  <a href="post.html?id=' + p.id + '" class="btn btn-outline btn-sm">👁️ View</a> ' +
                '  <a href="edit-post.html?id=' + p.id + '" class="btn btn-secondary btn-sm">✏️ Edit</a>' +
                '</td>';
            tbody.appendChild(tr);
        });
    }

    function initDashboardPage() {
        var loadingEl = document.getElementById('dash-loading');
        var statsEl = document.getElementById('dash-stats');
        var contentEl = document.getElementById('dash-content');
        var tbody = document.getElementById('dash-recent-tbody');

        if (loadingEl) loadingEl.style.display = 'none';
        if (statsEl) statsEl.style.display = 'grid';
        if (contentEl) contentEl.style.display = 'block';

        var statTotal = document.getElementById('stat-total-posts');
        var statPub = document.getElementById('stat-published-posts');
        var statDraft = document.getElementById('stat-draft-posts');
        var statViews = document.getElementById('stat-total-views');

        if (statTotal) statTotal.textContent = SAMPLE_ARTICLES.length;
        if (statPub) statPub.textContent = SAMPLE_ARTICLES.length;
        if (statDraft) statDraft.textContent = '0';
        if (statViews) statViews.textContent = Number(11690).toLocaleString();

        if (tbody) {
            tbody.innerHTML = '';
            SAMPLE_ARTICLES.slice(0, 4).forEach(function (p) {
                var tr = document.createElement('tr');
                tr.innerHTML = '' +
                    '<td><strong>' + escapeHtml(p.title) + '</strong></td>' +
                    '<td><span class="badge badge-category">' + escapeHtml(p.category_name) + '</span></td>' +
                    '<td><span class="badge badge-published">Published</span></td>' +
                    '<td>👁️ ' + Number(p.views).toLocaleString() + '</td>' +
                    '<td>' + new Date(p.published_at).toLocaleDateString() + '</td>' +
                    '<td style="text-align:right;"><a href="post.html?id=' + p.id + '" class="btn btn-outline btn-sm">View</a></td>';
                tbody.appendChild(tr);
            });
        }
    }

    function initEditorPage() {
        var categorySelect = document.getElementById('post-category');
        if (categorySelect) {
            categorySelect.innerHTML = '<option value="">-- No Category (Optional) --</option>';
            SAMPLE_CATEGORIES.forEach(function (cat) {
                var opt = document.createElement('option');
                opt.value = cat.id;
                opt.textContent = cat.name;
                categorySelect.appendChild(opt);
            });
        }

        var contentTextarea = document.getElementById('post-content');
        var wordCountSpan = document.getElementById('content-word-count');
        function updateWordCount() {
            if (!contentTextarea || !wordCountSpan) return;
            var text = (contentTextarea.value || '').trim();
            var words = text ? text.split(/\s+/).length : 0;
            wordCountSpan.textContent = words + ' word' + (words === 1 ? '' : 's');
        }

        if (contentTextarea) {
            contentTextarea.addEventListener('input', updateWordCount);
        }

        // Toolbar buttons
        var toolbarBtns = document.querySelectorAll('.toolbar-btn');
        toolbarBtns.forEach(function (btn) {
            btn.onclick = function (e) {
                e.preventDefault();
                if (!contentTextarea) return;
                var action = btn.getAttribute('data-action');
                var start = contentTextarea.selectionStart;
                var end = contentTextarea.selectionEnd;
                var val = contentTextarea.value;
                var selected = val.substring(start, end);
                var replacement = '';

                switch (action) {
                    case 'bold': replacement = '**' + (selected || 'bold text') + '**'; break;
                    case 'italic': replacement = '*' + (selected || 'italic text') + '*'; break;
                    case 'heading': replacement = '\n## ' + (selected || 'Heading') + '\n'; break;
                    case 'list': replacement = '\n- ' + (selected || 'List item') + '\n'; break;
                    case 'quote': replacement = '\n> ' + (selected || 'Quote') + '\n'; break;
                    case 'code': replacement = '`' + (selected || 'code') + '`'; break;
                    case 'link': replacement = '[' + (selected || 'Link text') + '](https://example.com)'; break;
                }

                contentTextarea.value = val.substring(0, start) + replacement + val.substring(end);
                contentTextarea.focus();
                contentTextarea.setSelectionRange(start + replacement.length, start + replacement.length);
                updateWordCount();
            };
        });

        // AI Summary button
        var genSummaryBtn = document.getElementById('generate-ai-summary-btn') || document.getElementById('generate-summary-btn') || document.getElementById('ai-generate-btn');
        var summaryTextarea = document.getElementById('ai-summary-text') || document.getElementById('post-summary');
        var summaryBadge = document.getElementById('summary-status-badge');
        if (genSummaryBtn) {
            genSummaryBtn.onclick = function (e) {
                e.preventDefault();
                var text = contentTextarea ? contentTextarea.value.trim() : '';
                if (!text) {
                    alert('Please enter some article content first to generate an AI summary.');
                    return;
                }
                var firstSentence = text.split(/[.\n]/)[0].substring(0, 160).trim();
                var mockSummary = firstSentence ? (firstSentence + '.') : 'A concise overview highlighting the core principles and architectural decisions.';
                if (summaryTextarea) {
                    summaryTextarea.value = mockSummary;
                }
                if (summaryBadge) {
                    summaryBadge.textContent = 'ready';
                    summaryBadge.className = 'badge badge-published';
                }
                alert('✨ AI Summary generated successfully (Standalone Preview Mode)!');
            };
        }

        // If on edit-post.html, populate fields with sample article
        var rawFile = window.location.pathname.split('/').pop().toLowerCase();
        if (rawFile.indexOf('edit-post') !== -1) {
            var params = new URLSearchParams(window.location.search);
            var id = parseInt(params.get('id') || '1', 10);
            var article = SAMPLE_ARTICLES.find(function (a) { return a.id === id; }) || SAMPLE_ARTICLES[0];

            var titleInput = document.getElementById('post-title');
            var tagsInput = document.getElementById('post-tags');
            var metaCreated = document.getElementById('meta-created');
            var metaUpdated = document.getElementById('meta-updated');
            var metaPublished = document.getElementById('meta-published');
            var statusDraft = document.getElementById('status-radio-draft');
            var statusPublished = document.getElementById('status-radio-published');
            var statusBadge = document.getElementById('status-badge-container');

            if (titleInput) titleInput.value = article.title;
            if (categorySelect) categorySelect.value = article.category_id;
            if (tagsInput) tagsInput.value = article.tags;
            if (contentTextarea) contentTextarea.value = article.content;
            if (summaryTextarea) summaryTextarea.value = article.summary;
            if (summaryBadge) {
                summaryBadge.textContent = article.summary_status || 'ready';
                summaryBadge.className = 'badge badge-published';
            }
            if (statusPublished) statusPublished.checked = true;
            if (statusBadge) statusBadge.innerHTML = '<span class="badge badge-published">Published</span>';
            if (metaCreated) metaCreated.textContent = new Date(article.published_at).toLocaleString();
            if (metaUpdated) metaUpdated.textContent = new Date(article.published_at).toLocaleString();
            if (metaPublished) metaPublished.textContent = new Date(article.published_at).toLocaleString();
            updateWordCount();
        }

        // Action buttons
        var form = document.getElementById('create-post-form') || document.getElementById('edit-post-form');
        if (form) {
            form.onsubmit = function (e) {
                e.preventDefault();
                alert('💾 Form submitted in Preview Mode!\n\nTo save articles permanently to MySQL, run run-infinity-blog.bat or host with XAMPP.');
            };
        }
        var updateBtn = document.getElementById('update-post-btn');
        if (updateBtn) {
            updateBtn.onclick = function (e) {
                e.preventDefault();
                alert('💾 Article updated in Preview Mode!\n\nTo persist changes to MySQL, run run-infinity-blog.bat or host with XAMPP.');
            };
        }
        var togglePubBtn = document.getElementById('toggle-publish-btn');
        if (togglePubBtn) {
            togglePubBtn.onclick = function (e) {
                e.preventDefault();
                alert('🔄 Publish state toggled (Preview Mode)!');
            };
        }
    }

    function initAdminPage() {
        var statPosts = document.getElementById('admin-stat-posts');
        var statAuthors = document.getElementById('admin-stat-authors');
        var statAdmins = document.getElementById('admin-stat-admins');
        var statCategories = document.getElementById('admin-stat-categories');

        if (statPosts) statPosts.textContent = SAMPLE_ARTICLES.length;
        if (statAuthors) statAuthors.textContent = SAMPLE_AUTHORS.length;
        if (statAdmins) statAdmins.textContent = '1';
        if (statCategories) statCategories.textContent = SAMPLE_CATEGORIES.length;

        // Admin Tab Switching
        var tabs = document.querySelectorAll('.admin-nav-tab');
        var contents = document.querySelectorAll('.admin-tab-content');
        tabs.forEach(function (tab) {
            tab.onclick = function () {
                var targetId = tab.getAttribute('data-tab');
                tabs.forEach(function (t) { t.classList.remove('active'); });
                contents.forEach(function (c) { c.style.display = 'none'; });
                tab.classList.add('active');
                var targetEl = document.getElementById(targetId);
                if (targetEl) targetEl.style.display = 'block';
            };
        });

        // Moderation Table
        var postsTbody = document.getElementById('admin-posts-tbody');
        if (postsTbody) {
            postsTbody.innerHTML = '';
            SAMPLE_ARTICLES.forEach(function (p) {
                var tr = document.createElement('tr');
                tr.innerHTML = '' +
                    '<td><strong>' + escapeHtml(p.title) + '</strong></td>' +
                    '<td>' + escapeHtml(p.author_name) + '</td>' +
                    '<td><span class="badge badge-category">' + escapeHtml(p.category_name) + '</span></td>' +
                    '<td><span class="badge badge-published">Published</span></td>' +
                    '<td>' + new Date(p.published_at).toLocaleDateString() + '</td>' +
                    '<td style="text-align:right;">' +
                    '  <a href="post.html?id=' + p.id + '" class="btn btn-outline btn-sm">View</a> ' +
                    '  <a href="edit-post.html?id=' + p.id + '" class="btn btn-secondary btn-sm">Edit</a>' +
                    '</td>';
                postsTbody.appendChild(tr);
            });
        }

        // Users Table
        var usersTbody = document.getElementById('admin-users-tbody');
        if (usersTbody) {
            usersTbody.innerHTML = '';
            SAMPLE_AUTHORS.forEach(function (u) {
                var tr = document.createElement('tr');
                tr.innerHTML = '' +
                    '<td><code>@' + escapeHtml(u.display_name.toLowerCase().replace(/\s+/g, '')) + '</code></td>' +
                    '<td><strong>' + escapeHtml(u.display_name) + '</strong></td>' +
                    '<td>' + escapeHtml(u.display_name.toLowerCase().replace(/\s+/g, '') + '@infinityblog.local') + '</td>' +
                    '<td><span class="badge ' + (u.role === 'admin' ? 'badge-admin' : 'badge-category') + '">' + escapeHtml(u.role) + '</span></td>' +
                    '<td><span class="badge badge-published">Active</span></td>' +
                    '<td>' + new Date(u.created_at).toLocaleDateString() + '</td>' +
                    '<td style="text-align:right;">' +
                    '  <a href="author.html?id=' + u.id + '" class="btn btn-outline btn-sm">Profile</a>' +
                    '</td>';
                usersTbody.appendChild(tr);
            });
        }

        // Audit Logs Table
        var auditTbody = document.getElementById('admin-audit-tbody');
        if (auditTbody) {
            var sampleLogs = [
                { action: 'auth.login.success', actor: 'Sounak Sikdar', ip: '127.0.0.1', details: 'Successful session established for administrator', date: 'Just now' },
                { action: 'post.publish', actor: 'Sounak Sikdar', ip: '127.0.0.1', details: 'Article #1 published to public feed', date: '2 hours ago' },
                { action: 'post.ai_summary.generate', actor: 'Jane Doe', ip: '127.0.0.1', details: 'Generated deterministic extractive summary for article #2', date: '5 hours ago' },
                { action: 'user.signup.success', actor: 'John Smith', ip: '127.0.0.1', details: 'New author registration completed', date: '1 day ago' },
                { action: 'category.create', actor: 'Sounak Sikdar', ip: '127.0.0.1', details: 'Created category Cybersecurity', date: '3 days ago' }
            ];
            auditTbody.innerHTML = '';
            sampleLogs.forEach(function (l) {
                var tr = document.createElement('tr');
                tr.innerHTML = '' +
                    '<td><code style="font-size:0.85rem;color:var(--color-primary);">' + escapeHtml(l.action) + '</code></td>' +
                    '<td>' + escapeHtml(l.actor) + '</td>' +
                    '<td><code>' + escapeHtml(l.ip) + '</code></td>' +
                    '<td style="font-size:0.85rem;color:var(--color-text-muted);">' + escapeHtml(l.details) + '</td>' +
                    '<td style="font-size:0.85rem;color:var(--color-text-muted);">' + escapeHtml(l.date) + '</td>';
                auditTbody.appendChild(tr);
            });
        }

        var addBtn = document.getElementById('open-add-user-btn');
        if (addBtn) {
            addBtn.onclick = function () {
                alert('👤 User Provisioning Modal (Preview Mode)\n\nIn Standalone Mode, sample users are rendered directly. Run run-infinity-blog.bat for full MySQL CRUD operations.');
            };
        }
    }

    function initLoginPage() {
        var form = document.getElementById('login-form');
        var errAlert = document.getElementById('login-error-alert');
        var succAlert = document.getElementById('login-success-alert');

        // Toggle password show/hide
        var toggleBtn = document.getElementById('toggle-password-btn') || document.querySelector('.toggle-password-btn');
        var pwdInput = document.getElementById('login-password') || document.getElementById('password');
        if (toggleBtn && pwdInput) {
            toggleBtn.onclick = function (e) {
                e.preventDefault();
                if (pwdInput.type === 'password') {
                    pwdInput.type = 'text';
                    toggleBtn.textContent = '👁️‍🗨️';
                } else {
                    pwdInput.type = 'password';
                    toggleBtn.textContent = '👁️';
                }
            };
        }

        if (form) {
            form.onsubmit = function (e) {
                e.preventDefault();
                if (succAlert) {
                    succAlert.style.display = 'block';
                    succAlert.innerHTML = '<strong>Preview Mode Active:</strong> You are opening this page via <code>file:///</code>. To log in with real MySQL database accounts, run <code>run-infinity-blog.bat</code> or host in XAMPP.<br><br>👉 <a href="dashboard.html" style="font-weight:700;text-decoration:underline;">Click here to preview the Author Dashboard</a> or <a href="admin.html" style="font-weight:700;text-decoration:underline;">Admin Panel</a>.';
                } else {
                    alert('Preview Mode: Run run-infinity-blog.bat for real database authentication.\n\nClick OK to visit the Dashboard preview.');
                    window.location.href = 'dashboard.html';
                }
                if (errAlert) errAlert.style.display = 'none';
            };
        }
    }

    function initSignupPage() {
        var form = document.getElementById('signup-form');
        var errAlert = document.getElementById('signup-error-alert');
        var succAlert = document.getElementById('signup-success-alert');

        // Toggle password
        var toggleBtns = document.querySelectorAll('.toggle-password-btn');
        toggleBtns.forEach(function (btn) {
            btn.onclick = function (e) {
                e.preventDefault();
                var targetId = btn.getAttribute('data-target');
                var inp = targetId ? document.getElementById(targetId) : null;
                if (!inp) {
                    var parent = btn.parentElement;
                    inp = parent ? parent.querySelector('input') : null;
                }
                if (inp) {
                    inp.type = inp.type === 'password' ? 'text' : 'password';
                }
            };
        });

        if (form) {
            form.onsubmit = function (e) {
                e.preventDefault();
                if (succAlert) {
                    succAlert.style.display = 'block';
                    succAlert.innerHTML = '<strong>Preview Mode Active:</strong> Account registration previewed successfully! To store users permanently in MySQL, run <code>run-infinity-blog.bat</code> or host with XAMPP.<br><br>👉 <a href="dashboard.html" style="font-weight:700;text-decoration:underline;">Click here to preview the Author Dashboard</a>.';
                } else {
                    alert('Preview Mode: Run run-infinity-blog.bat for full MySQL user registration.\n\nClick OK to visit the Dashboard preview.');
                    window.location.href = 'dashboard.html';
                }
                if (errAlert) errAlert.style.display = 'none';
            };
        }
    }

    function initProfilePage() {
        var loadingEl = document.getElementById('profile-loading');
        var contentEl = document.getElementById('profile-content');
        if (loadingEl) loadingEl.style.display = 'none';
        if (contentEl) contentEl.style.display = 'block';

        var user = SAMPLE_AUTHORS[0];
        var profUsername = document.getElementById('prof-username');
        var profEmail = document.getElementById('prof-email');
        var profDisplayName = document.getElementById('prof-display-name');
        var profBio = document.getElementById('prof-bio');
        var profRole = document.getElementById('prof-role');
        var viewBtn = document.getElementById('view-public-profile-btn');

        var prevAvatar = document.getElementById('preview-avatar');
        var prevName = document.getElementById('preview-name');
        var prevRole = document.getElementById('preview-role');
        var prevBio = document.getElementById('preview-bio');

        if (profUsername) profUsername.value = 'sounak';
        if (profEmail) profEmail.value = 'sounak@infinityblog.local';
        if (profDisplayName) profDisplayName.value = user.display_name;
        if (profBio) profBio.value = user.bio;
        if (profRole) profRole.value = 'Administrator';
        if (viewBtn) viewBtn.href = 'author.html?id=' + user.id;

        if (prevAvatar) prevAvatar.textContent = user.display_name.charAt(0);
        if (prevName) prevName.textContent = user.display_name;
        if (prevRole) prevRole.textContent = 'Admin';
        if (prevBio) prevBio.textContent = user.bio;

        var form = document.getElementById('profile-form');
        if (form) {
            form.onsubmit = function (e) {
                e.preventDefault();
                alert('💾 Profile updated in Preview Mode!\n\nTo save changes to MySQL, run run-infinity-blog.bat or host with XAMPP.');
            };
        }
    }

    function initApp() {
        renderBanner();
        initHeader();

        var rawFile = window.location.pathname.split('/').pop().toLowerCase();
        var filename = rawFile.split('?')[0].split('#')[0] || 'index.html';
        if (!filename || filename === '') {
            filename = 'index.html';
        }

        if (filename === 'post.html') {
            initPostPage();
        } else if (filename === 'posts.html') {
            initPostsPage();
        } else if (filename === 'categories.html') {
            initCategoriesPage();
        } else if (filename === 'authors.html') {
            initAuthorsPage();
        } else if (filename === 'author.html') {
            initAuthorDetailPage();
        } else if (filename === 'dashboard.html') {
            initDashboardPage();
        } else if (filename === 'create-post.html' || filename === 'edit-post.html') {
            initEditorPage();
        } else if (filename === 'admin.html') {
            initAdminPage();
        } else if (filename === 'profile.html') {
            initProfilePage();
        } else if (filename === 'login.html') {
            initLoginPage();
        } else if (filename === 'signup.html') {
            initSignupPage();
        } else {
            initIndexPage();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initApp);
    } else {
        initApp();
    }
})();
