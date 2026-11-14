(function (root) {
    'use strict';

    // Capture our bundled parser so another script cannot change it later.
    var parser = root.csstree;

    root.wpacuAlternativeAssetsCssLint = function (text, messages) {
        if (!parser) { return []; }
        messages = messages || {};
        var errors = [];
        var seen = {};
        var lineStarts = [0];
        for (var i = 0; i < text.length; i++) {
            if (text[i] === '\r') {
                if (text[i + 1] === '\n') { i++; }
                lineStarts.push(i + 1);
            } else if (text[i] === '\n' || text[i] === '\f') {
                lineStarts.push(i + 1);
            }
        }

        function report(offset, message) {
            offset = Math.max(0, Math.min(text.length, offset || 0));
            if (seen[offset] || errors.length >= 50) { return; }
            seen[offset] = true;
            var low = 0;
            var high = lineStarts.length;
            while (low + 1 < high) {
                var middle = (low + high) >> 1;
                if (lineStarts[middle] <= offset) { low = middle; } else { high = middle; }
            }
            var column = offset - lineStarts[low];
            errors.push({from: {line: low, ch: column}, to: {line: low, ch: column + 1}, message: message, severity: 'error'});
        }

        // CSS parsers recover from missing closing tokens at EOF. Surface these
        // mistakes in the editor using tokens, without misreading comments,
        // strings, escaped identifiers or braces inside custom-property values.
        var types = parser.tokenTypes;
        var open = {};
        open[types.LeftCurlyBracket] = types.RightCurlyBracket;
        open[types.LeftSquareBracket] = types.RightSquareBracket;
        open[types.LeftParenthesis] = types.RightParenthesis;
        open[types.Function] = types.RightParenthesis;
        var close = {};
        close[types.RightCurlyBracket] = '}';
        close[types.RightSquareBracket] = ']';
        close[types.RightParenthesis] = ')';
        var stack = [];

        function hasClosingCharacter(start, end, character) {
            if (end <= start + 1 || text[end - 1] !== character) { return false; }
            var slashes = 0;
            for (var index = end - 2; index > start && text[index] === '\\'; index--) { slashes++; }
            return slashes % 2 === 0;
        }

        parser.tokenize(text, function (type, start, end) {
            if (open[type]) {
                stack.push({expected: open[type], offset: start});
            } else if (close[type]) {
                if (stack.length && stack[stack.length - 1].expected === type) {
                    stack.pop();
                } else {
                    report(start, (messages.unexpectedClosing || 'Unexpected closing token: %s').replace('%s', close[type]));
                }
            } else if (type === types.BadString || (type === types.String && !hasClosingCharacter(start, end, text[start]))) {
                report(start, messages.unclosedString || 'Unclosed or invalid CSS string.');
            } else if (type === types.Comment && (end - start < 4 || text.slice(end - 2, end) !== '*/')) {
                report(start, messages.unclosedComment || 'Unclosed CSS comment.');
            } else if (type === types.BadUrl || (type === types.Url && !hasClosingCharacter(start, end, ')'))) {
                report(start, messages.invalidUrl || 'Unclosed or invalid url().');
            }
        });
        stack.forEach(function (entry) {
            report(entry.offset, (messages.missingClosing || 'Missing closing token: %s').replace('%s', close[entry.expected]));
        });

        try {
            parser.parse(text, {
                positions: false,
                onParseError: function (error) { report(error.offset, error.message); }
            });
        } catch (error) {
            if (typeof error.offset === 'number') { report(error.offset, error.message); }
        }
        return errors.sort(function (a, b) { return a.from.line - b.from.line || a.from.ch - b.from.ch; });
    };
})(window);
