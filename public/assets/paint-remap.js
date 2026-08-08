(function (root) {
  'use strict';

  function charsFromText(text) {
    return Array.from(text || '');
  }

  /**
   * Character-level LCS remap of per-char styles from oldText onto newText.
   * @returns {{colors:string[], bolds:boolean[]}}
   */
  function remapStyles(oldText, oldColors, oldBolds, newText, insertColor, insertBold) {
    var oldChars = charsFromText(oldText);
    var newChars = charsFromText(newText);
    var n = oldChars.length;
    var m = newChars.length;
    var insertC = insertColor || 'default';
    var insertB = !!insertBold;

    var colors = [];
    var bolds = [];
    for (var z = 0; z < m; z++) {
      colors[z] = insertC;
      bolds[z] = insertB;
    }

    if (n === 0 || m === 0) {
      return { colors: colors, bolds: bolds };
    }

    // LCS lengths
    var dp = [];
    for (var i = 0; i <= n; i++) {
      dp[i] = [];
      for (var j = 0; j <= m; j++) {
        dp[i][j] = 0;
      }
    }
    for (i = 1; i <= n; i++) {
      for (j = 1; j <= m; j++) {
        if (oldChars[i - 1] === newChars[j - 1]) {
          dp[i][j] = dp[i - 1][j - 1] + 1;
        } else {
          dp[i][j] = Math.max(dp[i - 1][j], dp[i][j - 1]);
        }
      }
    }

    // Backtrack: copy styles for matches
    i = n;
    j = m;
    while (i > 0 && j > 0) {
      if (oldChars[i - 1] === newChars[j - 1]) {
        colors[j - 1] = oldColors[i - 1] || 'default';
        bolds[j - 1] = !!oldBolds[i - 1];
        i--;
        j--;
      } else if (dp[i - 1][j] >= dp[i][j - 1]) {
        i--;
      } else {
        j--;
      }
    }

    return { colors: colors, bolds: bolds };
  }

  root.GraffitiPaintRemap = {
    remapStyles: remapStyles,
    charsFromText: charsFromText,
  };
})(typeof globalThis !== 'undefined' ? globalThis : this);
