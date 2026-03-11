
</div><!-- /admin-content -->
</div><!-- /admin-main -->
</div><!-- /admin-layout -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function confirmAction(msg, formId) {
  if (confirm(msg)) { if (formId) document.getElementById(formId).submit(); return true; }
  return false;
}
function copyText(text, btn) {
  navigator.clipboard.writeText(text).then(() => {
    const orig = btn.innerHTML; btn.innerHTML = '✓ Copied!';
    setTimeout(() => btn.innerHTML = orig, 2000);
  });
}
setTimeout(() => { document.querySelectorAll('.alert-dismissible').forEach(el => bootstrap.Alert.getOrCreateInstance(el)?.close()); }, 5000);
</script>
</body></html>
