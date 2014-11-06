if (typeof _olytics !== 'object')  {
    _olytics = [];
}

if (typeof Olytics !== 'object') {

    Olytics = (function() {
        if (typeof console === "undefined" || typeof console.log === "undefined") {
            console = {};
            console.log = function() {};
        }

        if (typeof String.prototype.trim !== 'function') {
            String.prototype.trim = function() {
                return this.replace(/^\s+|\s+$/g, '');
            }
        }

        var
            // Alias frequently used globals for added minification
            documentAlias  = document,
            navigatorAlias = navigator,
            screenAlias    = screen,
            windowAlias    = window,

            // Encode
            encodeWrapper = windowAlias.encodeURIComponent,

            // Decode
            decodeWrapper = windowAlias.decodeURIComponent,

            // URL Decode
            urlencode = escape,
            urldecode = unescape,

            // Async Tracker
            asyncTracker,

            // Iterator
            i,

            Olytics;

        /**
         *
         *  Base64 encode / decode
         *  http://www.webtoolkit.info/
         *
        **/
        var Base64 = {

            // private property
            _keyStr : "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=",

            // public method for encoding
            encode : function (input) {
                var output = "";
                var chr1, chr2, chr3, enc1, enc2, enc3, enc4;
                var i = 0;

                input = Base64._utf8_encode(input);

                while (i < input.length) {

                    chr1 = input.charCodeAt(i++);
                    chr2 = input.charCodeAt(i++);
                    chr3 = input.charCodeAt(i++);

                    enc1 = chr1 >> 2;
                    enc2 = ((chr1 & 3) << 4) | (chr2 >> 4);
                    enc3 = ((chr2 & 15) << 2) | (chr3 >> 6);
                    enc4 = chr3 & 63;

                    if (isNaN(chr2)) {
                        enc3 = enc4 = 64;
                    } else if (isNaN(chr3)) {
                        enc4 = 64;
                    }

                    output = output +
                    this._keyStr.charAt(enc1) + this._keyStr.charAt(enc2) +
                    this._keyStr.charAt(enc3) + this._keyStr.charAt(enc4);

                }

                return output;
            },

            // public method for decoding
            decode : function (input) {
                var output = "";
                var chr1, chr2, chr3;
                var enc1, enc2, enc3, enc4;
                var i = 0;

                input = input.replace(/[^A-Za-z0-9\+\/\=]/g, "");

                while (i < input.length) {

                    enc1 = this._keyStr.indexOf(input.charAt(i++));
                    enc2 = this._keyStr.indexOf(input.charAt(i++));
                    enc3 = this._keyStr.indexOf(input.charAt(i++));
                    enc4 = this._keyStr.indexOf(input.charAt(i++));

                    chr1 = (enc1 << 2) | (enc2 >> 4);
                    chr2 = ((enc2 & 15) << 4) | (enc3 >> 2);
                    chr3 = ((enc3 & 3) << 6) | enc4;

                    output = output + String.fromCharCode(chr1);

                    if (enc3 != 64) {
                        output = output + String.fromCharCode(chr2);
                    }
                    if (enc4 != 64) {
                        output = output + String.fromCharCode(chr3);
                    }

                }

                output = Base64._utf8_decode(output);

                return output;

            },

            // private method for UTF-8 encoding
            _utf8_encode : function (string) {
                string = string.replace(/\r\n/g,"\n");
                var utftext = "";

                for (var n = 0; n < string.length; n++) {

                    var c = string.charCodeAt(n);

                    if (c < 128) {
                        utftext += String.fromCharCode(c);
                    }
                    else if((c > 127) && (c < 2048)) {
                        utftext += String.fromCharCode((c >> 6) | 192);
                        utftext += String.fromCharCode((c & 63) | 128);
                    }
                    else {
                        utftext += String.fromCharCode((c >> 12) | 224);
                        utftext += String.fromCharCode(((c >> 6) & 63) | 128);
                        utftext += String.fromCharCode((c & 63) | 128);
                    }

                }

                return utftext;
            },

            // private method for UTF-8 decoding
            _utf8_decode : function (utftext) {
                var string = "";
                var i = 0;
                var c = c1 = c2 = 0;

                while ( i < utftext.length ) {

                    c = utftext.charCodeAt(i);

                    if (c < 128) {
                        string += String.fromCharCode(c);
                        i++;
                    }
                    else if((c > 191) && (c < 224)) {
                        c2 = utftext.charCodeAt(i+1);
                        string += String.fromCharCode(((c & 31) << 6) | (c2 & 63));
                        i += 2;
                    }
                    else {
                        c2 = utftext.charCodeAt(i+1);
                        c3 = utftext.charCodeAt(i+2);
                        string += String.fromCharCode(((c & 15) << 12) | ((c2 & 63) << 6) | (c3 & 63));
                        i += 3;
                    }

                }

                return string;
            }

        }

        function isObject(property)
        {
            return typeof property === 'object';
        }

        function isArray(obj)
        {
            return Object.prototype.toString.call(obj) === '[object Array]';
        }

        function isFunction(property)
        {
            return typeof property === 'function';
        }

        function isDefined(property)
        {
            var propertyType = typeof property;
            return propertyType !== 'undefined';
        }

        function isString(property)
        {
            return typeof property === 'string' || property instanceof String;
        }

        function getReferrer()
        {
            var referrer = '';
            try {
                referrer = windowAlias.top.document.referrer;
            } catch (e) {
                if (windowAlias.parent) {
                    try {
                        referrer = windowAlias.parent.document.referrer;
                    } catch (e2) {
                        referrer = '';
                    }
                }
            }
            if (referrer === '') {
                referrer = documentAlias.referrer;
            }
            return referrer;
        }

        function getProtocolScheme(url)
        {
            var e = new RegExp('^([a-z]+):'),
            matches = e.exec(url);
            return matches ? matches[1] : null;
        }

        function getHostName(url)
        {
            // scheme : // [username [: password] @] hostame [: port] [/ [path] [? query] [# fragment]]
            var e = new RegExp('^(?:(?:https?|ftp):)/*(?:[^@]+@)?([^:/#]+)'),
            matches = e.exec(url);
            return matches ? matches[1] : url;
        }

        function getRootDomain(url)
        {
            var parser = documentAlias.createElement('a');
            parser.href = url;
            return parser.hostname;
        }

        function getPathname(url)
        {
            var parser = documentAlias.createElement('a');
            parser.href = url;
            return parser.pathname;
        }

        function getParameter(url, name)
        {
            var regexSearch = "[\\?&#]" + name + "=([^&#]*)";
            var regex = new RegExp(regexSearch);
            var results = regex.exec(url);
            return results ? decodeWrapper(results[1]) : '';
        }

        function cleanDomainName(domain)
        {
            var dl = domain.length;

            // remove trailing '.'
            if (domain.charAt(--dl) === '.') {
                domain = domain.slice(0, dl);
            }
            // remove leading '*'
            if (domain.slice(0, 2) === '*.') {
                domain = domain.slice(1);
            }
            return domain;
        }

        function cleanTitle(title)
        {
            title = title && title.text ? title.text : title;
            if (!isString(title)) {
                var tmp = documentAlias.getElementsByTagName('title');

                if (tmp && isDefined(tmp[0])) {
                    title = tmp[0].text;
                }
            }
            return title;
        }

        function rand() {
            return Math.floor(Math.random() * 9999999999) + ""
        }

        function apply()
        {
            var i, f, parameterArray;
            for (i = 0; i < arguments.length; i += 1)  {
                parameterArray = arguments[i];
                f = parameterArray.shift();

                // console.log(f);

                if (isString(f)) {
                    asyncTracker[f].apply(asyncTracker, parameterArray);
                } else {
                    f.apply(asyncTracker, parameterArray);
                }
            }
        }

        function RelatedEntity(type, clientId, keyValues, relFields)
        {

            this.setType = function(value)
            {
                this.type = (isString(value)) ? value.toLowerCase() : '';
            }

            this.setClientId = function(value)
            {
                this.clientId = (isDefined(value)) ? value : null;
            }

            this.setKeyValues = function(value)
            {
                this.keyValues = (isObject(value)) ? value : {};
            }

            this.setRelFields = function(value)
            {
                this.relFields = (isObject(value)) ? value : {};
            }

            this.hydrate = function(entity)
            {
                if (isObject(entity)) {
                    if (isDefined(entity.type)) this.setType(entity.type);
                    if (isDefined(entity.clientId)) this.setClientId(entity.clientId);
                    if (isDefined(entity.keyValues)) this.setKeyValues(entity.keyValues);
                    if (isDefined(entity.relFields)) this.setRelFields(entity.relFields);
                }
                return this;
            }

            this.isValid = function()
            {
                if (this.type.length == 0) return false;
                if (this.clientId === null) return false;
                return true;
            }

            this.init = function()
            {
                this.setType(type);
                this.setClientId(clientId);
                this.setKeyValues(keyValues);
                this.setRelFields(relFields);
            }

            this.init();
        }

        function Entity(type, clientId, keyValues, relatedTo)
        {
            this.setType = function(value)
            {
                this.type = (isString(value)) ? value.toLowerCase() : '';
            }

            this.setClientId = function(value)
            {
                this.clientId = (isDefined(value)) ? value : null;
            }

            this.setKeyValues = function(value)
            {
                this.keyValues = (isObject(value)) ? value : {};
            }

            this.setRelatedTo = function(value)
            {
                this.relatedTo = [];
                if (isObject(value)) {
                    for (var n = 0; n < value.length; n++) {
                        var relatedEntity = new RelatedEntity();
                        relatedEntity.hydrate(value[n]);
                        if (relatedEntity.isValid) {
                            this.relatedTo.push(relatedEntity);
                        }
                    }
                }
            }

            this.hydrate = function(entity)
            {
                if (isObject(entity)) {
                    if (isDefined(entity.type)) this.setType(entity.type);
                    if (isDefined(entity.clientId)) this.setClientId(entity.clientId);
                    if (isDefined(entity.keyValues)) this.setKeyValues(entity.keyValues);
                    if (isDefined(entity.relatedTo)) this.setRelatedTo(entity.relatedTo);
                }
                return this;
            }

            this.isValid = function()
            {
                if (this.type.length == 0) return false;
                if (this.clientId === null) return false;
                return true;
            }

            this.init = function()
            {
                this.setType(type);
                this.setClientId(clientId);
                this.setKeyValues(keyValues);
                this.setRelatedTo(relatedTo);
            }

            this.init();
        }

        function Event(action, entity, relatedEntities, data, createdAt)
        {
            this.setAction = function(value)
            {
                this.action = (isString(value)) ? value.toLowerCase() : '';
            }

            this.setData = function(value)
            {
                this.data = (isObject(value)) ? value : {};
            }

            this.setCreatedAt = function(value)
            {
                var d = new Date();
                this.createdAt = (value instanceof Date) ? value.toGMTString() : d.toGMTString();
            }

            this.setRelatedEntities = function(value)
            {
                this.relatedEntities = [];
                if (isArray(value)) {
                    for (var n = 0; n < value.length; n++) {
                        if (value[n] instanceof Entity) {
                            if (value[n].isValid()) this.relatedEntities.push(value[n]);
                        } else if (isObject(value)) {
                            var e = new Entity();
                            e.hydrate(value[n]);
                            if (e.isValid()) this.relatedEntities.push(e);
                        }
                    }
                }
            }

            this.setEntity = function(value)
            {
                if (value instanceof Entity) {
                    this.entity = value;
                } else if (isObject(value)) {
                    var e = new Entity();
                    this.entity = e.hydrate(value);
                } else {
                    this.entity = null;
                }
            }

            this.isValid = function()
            {
                if (this.action.length == 0) return false;
                if (this.entity === null || this.entity.isValid() === false) return false;
                return true;
            }

            this.init = function()
            {
                this.setAction(action);
                this.setEntity(entity);
                this.setData(data)
                this.setCreatedAt(createdAt);
                this.setRelatedEntities(relatedEntities);
            }

            this.init();
        }

        function Request(trackerObject, primaryUrl)
        {
            var
                requestType = detectRequestSupport(),
                request = {
                    xhr: function(method, url, body) {
                        var xhr = new XMLHttpRequest();
                        xhr.open(method, url, true);
                        xhr.setRequestHeader('Content-Type', 'application/json');
                        xhr.send(body);
                    },
                    xdr: function(method, url, body) {
                        var xdr = new XDomainRequest();
                        xdr.open(method, url);

                        xdr.onprogress = function() { };
                        xdr.ontimeout = function() { };
                        xdr.onerror = function () { };
                        setTimeout(function() {
                            xdr.send(body);
                        }, 0);
                    },
                    jsonp: function(url) {
                        var callback = 'Olytics_' + rand();
                        if (url.match(/\?/)) {
                            url += '&callback=' + callback;
                        } else {
                            url += '?callback=' + callback;
                        }

                        var s = documentAlias.createElement('script');
                        s.type = 'text/javascript';
                        s.src = url;

                        window[callback] = function(data) {
                            documentAlias.getElementsByTagName('head')[0].removeChild(s);
                            delete window[callback];
                        };

                        documentAlias.getElementsByTagName('head')[0].appendChild(s);
                    }
                };

            this.send = function(trackerObject, primaryUrl)
            {
                switch (requestType) {
                    case 'xhr':
                        var body = JSON.stringify(trackerObject);
                        request.xhr('POST', primaryUrl, body);
                        break;
                    case 'xdr':
                        var body = JSON.stringify(trackerObject);
                        request.xdr('POST', primaryUrl, body);
                        break;
                    case 'jsonp':
                        var encoded = encodeWrapper(Base64.encode(JSON.stringify(trackerObject)));
                        var url = primaryUrl + '&enc=' + encoded;
                        request.jsonp(url);
                    default:
                        break;
                }
            }

            function detectRequestSupport()
            {
                if ((isObject(XMLHttpRequest) || isFunction(XMLHttpRequest)) && 'withCredentials' in new XMLHttpRequest()) {
                    return 'xhr';
                } else {
                    if (typeof XDomainRequest !== 'undefined') {
                        return 'xdr';
                    } else {
                        return 'jsonp';
                    }
                }
            }

            if (isDefined(trackerObject) && isDefined(primaryUrl)) {
                this.send(trackerObject, primaryUrl);
            }
        }

        function Tracker(endpoint)
        {
            var
                config = {
                    trackerDomain: 'http://olytics.cygnus.com',
                    baseEndpoint: '/events',
                    endpoint: endpoint || null,
                    domainName: documentAlias.domain,
                    cookie: {
                        visitor: {
                            key: '__olya',
                            expires: 1051200,
                        },
                        session: {
                            key: '__olyb',
                            expires: 30
                        },
                        customer: {
                            key: '__olyc',
                            expires: 1051200
                        },
                        acquisition: {
                            key: '__olyz',
                            expires: 259200
                        }
                    },
                    env: {},
                    page: {},
                    referrer: null,
                    disabled: false,
                    appendCustomer: false,
                },
                visitor = {},
                session = {},
                customer = {},
                setConfig = {
                    domainName: function(domain) {
                        config.domainName = (isDefined(domain)) ? cleanDomainName(domain) : documentAlias.domain;
                        return this;
                    },
                    referrer: function(referrer) {
                        config.referrer = (isDefined(referrer)) ? referrer : getReferrer();
                        return this;
                    },
                    cookieKey: function(key, ctype) {
                        if (isDefined(config.cookie[ctype])) {
                            config.cookie[ctype].key = key;
                        }
                        return this;
                    },
                    cookieExpires: function(expires, ctype) {
                        if (isDefined(config.cookie[ctype])) {
                            config.cookie[ctype].expires = expires;
                        }
                        return this;
                    },
                    pageUrl: function(href) {
                        config.page.url = (isDefined(href)) ? href : windowAlias.location.href;
                        return this;
                    },
                    pageTitle: function(title) {
                        title = (isDefined(title)) ? title : documentAlias.title;
                        config.page.title = decodeWrapper(cleanTitle(title));
                        return this;
                    },
                    pageType: function(type) {
                        config.page.type = (isDefined(type)) ? type : null;
                        return this;
                    },
                    envWindowSize: function() {
                        var pixelRatio = (new RegExp('Mac OS X.*Safari/')).test(navigatorAlias.userAgent) ? windowAlias.devicePixelRatio || 1 : 1;

                        var w = Math.max(documentAlias.documentElement.clientWidth, windowAlias.innerWidth || 0) * pixelRatio;
                        var h = Math.max(documentAlias.documentElement.clientHeight, windowAlias.innerHeight || 0) * pixelRatio;

                        config.env.windowRes = {};
                        config.env.windowRes.width = w;
                        config.env.windowRes.height = h;

                        return this;
                    },
                    envResolution: function(width, height) {
                        var pixelRatio = (new RegExp('Mac OS X.*Safari/')).test(navigatorAlias.userAgent) ? windowAlias.devicePixelRatio || 1 : 1;
                        config.env.res = {};

                        if (isDefined(width) && isDefined(height)) {
                            config.env.res.width = width;
                            config.env.res.height = height;
                        } else {
                            config.env.res.width = screenAlias.width * pixelRatio;
                            config.env.res.height = screenAlias.height * pixelRatio;
                        }
                        return this;
                    },
                    envTimezone: function(tz) {
                        var d = new Date();
                        config.env.tz = (isDefined(tz)) ? tz : d.getTimezoneOffset();
                        return this;
                    }
                }

            function init()
            {
                setDefaults();
            }

            function setDefaults()
            {
                setConfig
                    .referrer()
                    .pageUrl()
                    .pageTitle()
                    .pageType()
                    .envTimezone()
                    .envResolution()
                    .envWindowSize();
            }

            function getTrackerUrl()
            {
                return config.trackerDomain + config.baseEndpoint + config.endpoint
            }

            function hasVisitorCookie()
            {
                return (getVisitorCookie() !== null)
            }

            function getVisitorCookie()
            {
                var cookie = getCookie('visitor');
                if (cookie === null) return null;
                if (typeof cookie.id !== 'undefined') {
                    return cookie;
                } else {
                    return null;
                }
                return null;
            }

            function hasSessionCookie()
            {
                return (getSessionCookie() !== null)
            }

            function getSessionCookie()
            {
                var cookie = getCookie('session');
                if (cookie === null) return null;
                if (typeof cookie.id !== 'undefined') {
                    return cookie;
                } else {
                    return null;
                }
                return null;
            }

            function hasCustomerCookie()
            {
                return (getCustomerCookie() !== null)
            }

            function getCustomerCookie()
            {
                var cookie = getCookie('customer');
                if (cookie === null) return null;
                if (typeof cookie.id !== 'undefined') {
                    return cookie;
                } else {
                    return null;
                }
                return null;
            }

            function createNewVisitor()
            {
                var visitor = {
                    id: uuid.v4()
                };

                if (hasCustomerCookie()) {
                    var customer = getCookie('customer');
                    visitor.customerId = customer.id;
                    setCustomer(customer);
                }

                setVisitor(visitor);
                createNewSession(visitor);
            }

            function createNewSession()
            {
                var d = new Date();

                var session = {
                    id: uuid.v4(),
                    createdAt: d.toGMTString()
                }
                setSession(session);
            }

            function detectSetVisitor()
            {
                config.appendCustomer = false;

                if (!hasVisitorCookie()) {
                    createNewVisitor();
                } else {
                    var visitor = getCookie('visitor');

                    if (hasCustomerCookie()) {
                        var customer = getCookie('customer');
                        setCustomer(customer);

                        if (isDefined(visitor.customerId)) {
                            if (visitor.customerId == customer.id) {
                                setVisitor(visitor);
                            } else {
                                createNewVisitor();
                            }
                        } else {
                            visitor.customerId = customer.id;
                            setVisitor(visitor);

                            // Flag that request should update sessions
                            config.appendCustomer = true;
                        }

                    } else {
                        setVisitor(visitor);
                    }

                    if (hasSessionCookie()) {
                        var session = getCookie('session');
                        // Check for end of day expiration
                        if (!sessionEndOfDay(session)) {
                           // Handle acquisition source here
                            setSession(session);
                        } else {
                            createNewSession();
                        }
                    } else {
                        createNewSession();
                    }
                }
            }

            function sessionEndOfDay(session)
            {
                if (isDefined(session.createdAt)) {
                    var
                        sessionDate = new Date(session.createdAt),
                        nowDate = new Date();
                    return (nowDate.getDate() > sessionDate.getDate());

                } else {
                    return true;
                }
            }

            function setVisitor(value)
            {
                visitor = value;
                setCookie('visitor', value);
            }

            function setCustomer(value)
            {
                customer = value;
                setCookie('customer', value);
            }

            function setSession(value)
            {
                session = value;
                setCookie('session', value);
            }

            function trackEvent(action, entity, relatedTo, data)
            {
                var e = new Event(action, entity, relatedTo, data);
                if (e.isValid()) {
                    logEvent(e);
                }
            }

            function getPageViewEvent()
            {
                return new Entity('page', '$hash::' + config.page.url, config.page);
            }

            function trackPageView()
            {
                trackEvent('view', getPageViewEvent());
            }

            function createTrackerObject(e)
            {
                var trackerObject = {
                    pid: config.pid,
                    session: session,
                    // container: getPageViewEvent(),
                    event: e,
                    appendCustomer: config.appendCustomer
                };
                trackerObject.session.visitorId = isDefined(visitor.id) ? visitor.id : null;
                trackerObject.session.customerId = isDefined(customer.id) ? customer.id : null;;
                trackerObject.session.env = config.env;
                return trackerObject;
            }

            function xhrResponseHandler()
            {

            }

            function logEvent(e)
            {
                detectSetVisitor();

                if (config.disabled === false && config.endpoint !== null) {

                    // console.log('logEvent fired');

                    var trackerObject = createTrackerObject(e);
                    var request = new Request(trackerObject, getTrackerUrl());
                }
            }


            function getCookie(ctype)
            {
                if (isDefined(config.cookie[ctype])) {
                    var
                        key = config.cookie[ctype].key,
                        cookies = documentAlias.cookie.split(';');

                    for (var i = 0; i < cookies.length; i++) {
                        var cookie = cookies[i].trim().split('=');
                        if (cookie[0] == key) {
                            var value = parseCookieValue(cookie[1]);
                            if (value) return value;
                        }
                    }
                    return null;
                }
                return null;
            }

            function parseCookieValue(value)
            {
                value = decodeWrapper(value);
                try {
                    return JSON.parse(value);
                } catch (e) {
                    return null;
                }
            }


            function setCookie(ctype, value)
            {

                if (isDefined(config.cookie[ctype])) {

                    // Expiration is in minutes, convert to milliseconds
                    var d = new Date();
                    d.setTime(d.getTime() + (config.cookie[ctype].expires * 60 * 1000));

                    var
                        key = config.cookie[ctype].key,
                        value = JSON.stringify(value),
                        expires = '; expires=' + d.toGMTString(),
                        domain = '; domain=' + config.domainName,
                        path = '; path=/';

                    if (value) {
                        // console.log('Setting cookie: ' + key);
                        documentAlias.cookie = encodeWrapper(key) + '=' + encodeWrapper(value) + expires + domain + path;
                    }
                }
            }

            init();

            return {
                config: config,
                _setDomainName: function(domain) {
                    setConfig.domainName(domain);
                },
                _setEndPoint: function (endpoint) {
                    config.endpoint = endpoint;
                },
                _setTrackerDomain: function (domain) {
                    config.trackerDomain = domain;
                },
                _setPage: function (title, url) {
                    setConfig.pageTitle(title);
                    setConfig.pageUrl(url);
                },
                _setReferrer: function (url) {
                    setConfig.referrer(url);
                },
                _setPageType: function (type) {
                    setConfig.pageType(type);
                },
                _trackEvent: function (action, entity, relatedTo, data) {
                    trackEvent(action, entity, relatedTo, data);
                },
                _trackPageview: function () {
                    trackPageView();
                },
                _setVisitorCookieName: function(cname) {
                    setConfig.cookieKey(cname, 'visitor');
                },
                _setSessionCookieName: function(cname) {
                    setConfig.cookieKey(cname, 'session');
                },
                _setCustomerCookieName: function(cname) {
                    setConfig.cookieKey(cname, 'customer');
                },
                _setAcquisitionCookieName: function(cname) {
                    setConfig.cookieKey(cname, 'acquisition');
                }
            }
        }

        function TrackerProxy()
        {
            return { push: apply };
        }

        asyncTracker = new Tracker();

        // find the call to setTrackerUrl or setProfileId (if any) and call them first
        for (i = 0; i < _olytics.length; i++) {
            if (_olytics[i][0] === '_setDomainName' || _olytics[i][0] == '_setTrackerDomain' || _olytics[i][0] == '_setEndPoint') {
                apply(_olytics[i]);
                delete _olytics[i];
            }
        }

        // apply the queue of actions
        for (i = 0; i < _olytics.length; i++) {
            if (_olytics[i]) {
                apply(_olytics[i]);
            }
        }

        _olytics = new TrackerProxy();

        Olytics =
        {
            createTracker: function (endpoint)
            {
                return new Tracker(endpoint);
            },

            getAsyncTracker: function ()
            {
                return asyncTracker;
            }
        };

        // Expose Olytics as an AMD module
        if (typeof define === 'function' && define.amd)
        {
            define(['olytics'], [], function () { return Olytics; });
        }

        return Olytics;

    }());

}