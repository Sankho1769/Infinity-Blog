const routes = [
  '/',
  '/categories',
  '/authors',
  '/explore',
  '/chat',
  '/messages',
  '/library',
  '/following',
  '/dashboard',
  '/posts/manage',
  '/posts/new',
  '/profile',
  '/admin/categories-page',
  '/admin/users-page',
  '/admin/audit-logs-page',
  '/admin/contact-messages-page',
  '/logout',
  '/login',
  '/register',
  '/?search=test'
];

async function main() {
  for (const r of routes) {
    const res = await fetch('http://localhost:8086' + r, { redirect: 'manual' });
    const loc = res.headers.get('location') || '';
    console.log(`${r.padEnd(30)} => ${res.status}${loc ? ' -> ' + loc : ''}`);
  }
}

main().catch(err => {
  console.error(err);
  process.exit(1);
});
