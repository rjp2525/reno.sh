export default defineNuxtRouteMiddleware((to, from) => {
  const getDepth = (path) => {
    return path.split('/').filter(seg => seg.length > 0).length;
  }

  const toDepth = getDepth(to.path);
  const fromDepth = getDepth(from.path);

  if (toDepth > fromDepth) {
    to.meta.pageTransition = { name: 'page', mode: 'out-in' };
    from.meta.pageTransition = { name: 'page', mode: 'out-in' };
  } else {
    to.meta.pageTransition = { name: 'page', mode: 'out-in' };
    from.meta.pageTransition = { name: 'page', mode: 'out-in' };
  }
});
