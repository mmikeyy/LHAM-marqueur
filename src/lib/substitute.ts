// Override substitute method
export const substitute = (s,o,fn?,preserve?) => {
    return s && s.replace ? s.replace(sub.pattern,sub.replacer(o,fn,preserve)) : s;
};


// shortcut
let sub = substitute as any;

// Static regex for matching {key} {key|input} patterns
sub.pattern = /\{\s*([^|{}]+?)\s*(?:\|([^\{}]*))?\s*\}/g;

// Replacer function factory
sub.replacer = function (o,fn,preserve) {
    return function (m,k,x) {
        var v = fn ? fn(o[k],o,k,x) : o[k];
        return v !== undefined ? v : (preserve ? m : '');
    }
};

// Wrapper to execute substitute until no more substitutions can be done
sub.recursive = function (s,o,fn,preserve) {
    var r = s,i=0;
    do {
        s = r;
        r = sub(s,o,fn,preserve);
    } while (r !== s && ++i < sub.recursive.MAX);
    return r;
};
// Recursion limit
sub.recursive.MAX = 100;

