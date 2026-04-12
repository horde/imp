var mb = document.getElementById('messageBody');
if (mb) {
    mb.addEventListener('IMP_Preview:loadedFromCache', function() { window.SyntaxHighlighter.highlight(); });
}
