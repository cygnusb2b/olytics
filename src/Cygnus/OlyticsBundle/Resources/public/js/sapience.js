
if (typeof _sapient !== 'object')  {
    var _sapient = [];
}

if (typeof String.prototype.trim !== 'function') {
    String.prototype.trim = function() {
        return this.replace(/^\s+|\s+$/g, '');
    }
}

/**
 *
 */
var Sapience = (function() {

    var
        Utils       = new Utils(),
        Debugger    = new Debugger(),
        Tracker     = new Tracker()
    ;

    init();

    _sapient = new Proxy();

    function ScrollTracker(selector)
    {
        var element = Utils.isString(selector) ? jQuery(selector) : jQuery(window);
        var bound = [];
        var delta = 5;

        init();

        this.bind = function(entity) {
            bound.push(entity);
            console.log(bound);
            return this;
        }

        function init()
        {
            if (!hasSupport()) {
                return;
            }

            var didScroll = false;
            var lastScrollTop = 0;
            var calcs = calculateElements();

            element.on('resize', function (e) {
                Debugger.info('ScrollTracker()', 'Window resized. Recalculated breakpoints.');
                calcs = calculateElements();
            });

            element.on('scroll', function (e) {
                didScroll = true;
            });

            setInterval(function() {
                if (didScroll && bound.length > 0) {
                    hasScrolled();
                    didScroll = false;
                }
            }, 250);

            var currentBreak;
            var lastBreak;

            function hasScrolled() {
                var
                    breaks = calcs.breaks,
                    st = element.scrollTop(),
                    sb = st + calcs.heights.viewport
                ;

                // Make sure they scroll more than delta
                if (Math.abs(lastScrollTop - st) <= delta) {
                    return;
                }

                currentBreak = getCurrentBreak(sb, breaks);
                var direction = st > lastScrollTop ? 'down' : 'up';

                if (currentBreak !== lastBreak && null !== currentBreak) {
                    Debugger.info('ScrollTracker()', 'Scroll breakpoint ' + currentBreak + ' of 4 reached. Direction: ' + direction);
                    sendEvents(currentBreak, direction);
                    lastBreak = currentBreak;
                }
                lastScrollTop = st;
            }
        }

        function sendEvents(breakpoint, direction)
        {
            var data = {
                top: (breakpoint - 1) * 25 + 1,
                bottom: breakpoint * 25,
                direction: direction
            };
            for (var i in bound) {
                _sapient.push(['_trackEvent', 'scroll', bound[i], undefined, data]);
            }
        }

        function getCurrentBreak(sb, breaks)
        {
            if (sb <= breaks[0]) {
                return null;
            } else if (sb > breaks[0] && sb <= breaks[1]) {
                // 1 - 25; 26 - 50; 51 - 75; 76 - 100
                return 1;
            } else if (sb > breaks[1] && sb <= breaks[2]) {
                return 2;
            } else if (sb > breaks[2] && sb <= breaks[3]) {
                return 3;
            } else {
                return 4;
            }
        }

        function calculateElements()
        {
            var page = $(document).height(), viewport = element.height();
            var calcs = {
                heights: {
                    page:       page,
                    viewport:   viewport,
                    zone:       Math.round((page - viewport) / 4)
                },
                breaks: {}
            };

            for (var i = 0; i < 4; i++) {
                var pixels = (calcs.heights.zone * i) + calcs.heights.viewport;
                calcs.breaks[i] = pixels;
            }
            return calcs;
        }

        function hasSupport()
        {
            if (!Utils.isDefined(window.jQuery)) {
                Debugger.warn('ScrollTracker()', 'jQuery must be loaded to enable scroll tracking. Tracking disabled.');
                return false;
            }

            var required = '1.4.3';
            if (versionIsAtLeast(required)) {
                return true;
            }

            Debugger.warn('ScrollTracker()', 'jQuery must be at least version ' + required + '. Tracking disabled.');
            return false;
        }

        function versionIsAtLeast(requested)
        {
            var
                requested = extractVersion(requested)
                actual = extractVersion(window.jQuery.fn.jquery)
            ;
            for (var i = 0; i < 3; i++) {

                if (requested[i] == actual[i]) {
                    continue;
                }
                return requested[i] < actual[i];
            }
            return true;
        }

        function extractVersion(version)
        {
            var version = version.split('.');
            if (version.length < 1) {
                throw 'The version must contain at least one part, e.g. "1" or "1.0", etc.';
            }
            var extracted = [];
            for (var i = 0; i < 3; i++) {
                extracted[i] = Utils.isDefined(version[i]) ? parseInt(version[i]) : 0;
            }
            return extracted;
        }
    }

    /**
     *
     */
    function Tracker()
    {
        var
            appendIdentity = false,
            config = new Config(),
            env = {
                res: Utils.getResolution(),
                tz: Utils.getTimezone(),
                windowRes: Utils.getWindowSize()
            },
            previousEvents = {},
            visitor, session, identity, campaign,
            scroll
        ;

        function init()
        {
            visitor = getCookie('visitor');
            session = getCookie('session');
            identity = getCookie('identity');
            campaign = getCampaign();

            refreshCookies();
        }

        function getTrackerUrl()
        {
            return config.get('trackerDomain') + config.get('baseEndpoint') + config.get('endpoint');
        }

        function trackEvent(action, entity, relatedTo, data)
        {
            logEvent(new Event(action, entity, relatedTo, data));
        }

        function trackScroll(entity)
        {
            if (!Utils.isDefined(scroll)) {
                scroll = new ScrollTracker(config.get('scrollSelector'));
            }
            scroll.bind(entity, elementId);
        }

        function resendLastEvent(action)
        {
            if (!previousEvents.hasOwnProperty(action)) {
                Debugger.warn('Tracker()', 'No previous events found for action "' + action + '"');
                return;
            }
            logEvent(previousEvents[action]);
        }

        function trackPageview()
        {
            trackEvent('view', getPageViewEntity());
        }

        function getPageViewEntity()
        {
            return new Entity('page', '$hash::' + config.get('page').url, config.get('page'));
        }

        function logEvent(e)
        {
            if (config.get('disabled')) {
                Debugger.error('Tracker()', 'The tracker is currently disabled. No events will fire.');
                return this;
            }

            if (!config.isValid()) {
                Debugger.error('Tracker()', 'The tracker configuration is invald. No events will fire.');
                return this;
            }

            init();
            if (e.isValid()) {
                var request = new Request(createRequestObject(e), getTrackerUrl());
                request.send();
                previousEvents[e.action] = e;
            }
        }

        function createRequestObject(e)
        {
            var r = {
                app: config.get('app'),
                appendCustomer: appendIdentity,
                event: e,
                session: session
            };

            r.session.campaign = hasCampaign() ? campaign : null;

            r.session.visitorId = Utils.isDefined(visitor.id) ? visitor.id : null;
            r.session.customerId = (hasIdentity() && Utils.isDefined(identity.id)) ? identity.id : null;
            r.session.env = env;
            return r;
        }

        function refreshCookies()
        {
            if (!hasVisitor()) {
                setVisitor(createNewVisitor());
                setSession(createNewSession());
                if (hasIdentity()) {
                    setIdentity(identity);
                }
                if (hasCampaign()) {
                    setCampaign(campaign);
                }
            } else {

                var v = visitor;

                if (hasIdentity()) {

                    setIdentity(identity);

                    if (visitorHasIdentityId()) {
                        if (v.customerId !== identity.id) {
                            Debugger.info('Tracker()', 'Visitor to identity mismatch. Reset cookies.');
                            v = createNewVisitor();
                            session = createNewSession();
                        }
                    } else {
                        Debugger.info('Tracker()', 'Identity not set to active visitor. Flag request to update all related sessions.');
                        appendIdentity = true;
                        appendIdentityToVisitor(v, identity.id);
                    }
                }

                setVisitor(v);

                var s;
                if (!hasSession()) {
                    s = createNewSession();
                } else if (sessionEndOfDay(session)) {
                    Debugger.info('Tracker()', 'Session end of day reached. Expire and create new.');
                    s = createNewSession();
                } else if (sessionUpdateReferringIdentity(session)) {
                    Debugger.info('Tracker()', 'A new referring identity was detected. Create new session.');
                    s = createNewSession();
                } else {
                    s = session;
                }

                if (hasCampaign()) {
                    if (hasCampaignCookie() && !campaignsMatch(campaign, getCampaignFromCookie())) {
                        Debugger.info('Tracker()', 'Campaigns have changed. Create new session and update campaign cookie.');
                        s = createNewSession();
                    }
                    setCampaign(campaign);
                }
                setSession(s);
            }
        }

        function createNewVisitor()
        {
            var visitor = {
                id: uuid.v4()
            };

            identityId = hasIdentity() ? identity.id : null;
            appendIdentityToVisitor(visitor, identityId);

            Debugger.info('Tracker()', 'Created a new visitor with id ' + visitor.id);
            return visitor;
        }

        function appendIdentityToVisitor(visitor, id)
        {
            Debugger.info('Tracker()', 'Appending identity id "' + id + '" to visitor');
            visitor.customerId = id;
        }

        function createNewSession()
        {
            var d = new Date();

            var session = {
                id: uuid.v4(),
                createdAt: d.toGMTString(),
                rcid: getReferringIdentityId()
            }
            Debugger.info('Tracker()', 'Created a new session with id ' + session.id);
            return session;
        }

        function setVisitor(value)
        {
            visitor = value;
            setCookie('visitor', value);
        }

        function setSession(value)
        {
            session = value;
            setCookie('session', value);
        }

        function setIdentity(value)
        {
            identity = value;
            setCookie('identity', value);
        }

        function setCampaign(value)
        {
            campaign = value;
            setCookie('campaign', value);
        }

        function hasVisitor()
        {
            return Utils.isDefined(visitor) && null !== visitor;
        }

        function hasSession()
        {
            return Utils.isDefined(session) && null !== session;
        }

        function hasIdentity()
        {
            return Utils.isDefined(identity) && null !== identity;
        }

        function hasCampaign()
        {
            return Utils.isDefined(campaign) && null !== campaign;
        }

        function hasReferringIdentityId()
        {
            return null !== getReferringIdentityId();
        }

        function getReferringIdentityId()
        {
            return Utils.url(window.location.href).getQueryParam(config.get('referringIdentityKey'));
        }

        function visitorHasIdentityId()
        {
            return null !== getVisitorIdentityId();
        }

        function getVisitorIdentityId()
        {
            if (!hasIdentity()) {
                return null;
            }
            return Utils.isDefined(visitor.customerId) ? visitor.customerId : null;
        }

        function sessionEndOfDay(session)
        {
            if (Utils.isDefined(session.createdAt)) {
                var
                    sessionDate = new Date(session.createdAt),
                    nowDate = new Date();
                return (nowDate.getDate() > sessionDate.getDate());

            } else {
                Debugger.warn('Tracker()', 'Unable to determine session end of day.');
                return true;
            }
        }

        function sessionUpdateReferringIdentity(session)
        {
            if (!hasReferringIdentityId()) {
                return false;
            }
            if (!sessionHasReferringIdentity(session)) {
                return true;
            }
            return getReferringIdentityId() !== session.rcid;
        }

        function sessionHasReferringIdentity(session)
        {
            return Utils.isDefined(session.rcid) && null !== session.rcid;
        }

        function getCampaign()
        {
            var
                configCampaign = getCampaignFromConfig(),
                queryCampaign  = getCampaignFromQuery(),
                cookieCampaign = getCampaignFromCookie()
            ;
            if (null !== configCampaign) {
                return configCampaign;
            }
            if (null !== queryCampaign) {
                return queryCampaign;
            }
            if (null !== cookieCampaign) {
                return cookieCampaign;
            }
            return null;
        }

        function getCampaignFromConfig()
        {
            var campaign = config.get('campaign');

            if (campaignObjValid(campaign)) {
                return cleanCampaign(campaign);
            }
            return null;
        }

        function getCampaignFromQuery()
        {
            var requestCampaign = {}, keys = config.get('campaignKeys');

            for (key in keys) {
                requestCampaign[key] = Utils.url(window.location.href).getQueryParam(keys[key]);
            }

            if (campaignObjValid(requestCampaign)) {
                return cleanCampaign(requestCampaign);
            }
            return null;
        }

        function getCampaignFromCookie()
        {
            var cookie = getCookie('campaign');
            if (cookie === null) return null;
            if (campaignObjValid(cookie)) {
                return cleanCampaign(cookie);
            }
            return null;
        }

        function hasCampaignCookie()
        {
            return null !== getCampaignFromCookie();
        }

        function campaignsMatch(c1, c2)
        {
            for (var key in config.get('campaignKeys')) {
                if (c1[key] !== c2[key]) {
                    return false;
                }
            }
            return true;
        }

        function campaignObjValid(obj)
        {
            var requiredKeys = ['source', 'medium', 'name'];
            for (var i in requiredKeys) {
                var key = requiredKeys[i];

                if (!Utils.isDefined(obj[key])) {
                    return false;
                }

                if (null === obj[key]) {
                    return false;
                }
            }
            return true;
        }

        function cleanCampaign(obj)
        {
            var cleaned = {};
            for (key in config.get('campaignKeys')) {
                cleaned[key] = Utils.isDefined(obj[key]) ? obj[key] : null;
            }
            return cleaned;
        }

        /**
         *
         */
        function setCookie(ctype, value)
        {
            var cookie = config.get('cookie');

            if (false === Utils.isDefined(cookie[ctype])) {
                Debugger.warn('Tracker()', 'Unable to set cookie. Cookie type "' + ctype + '" does not exist.');
                return;
            }
            var
                name = cookie[ctype].key,
                value = JSON.stringify(value),
                seconds = cookie[ctype].expires * 60, // Config is in minutes, convert to seconds
                domain = (config.get('useCookieDomain')) ? config.get('domainName') : null
            ;
            if (value) {
                Utils.setCookie(name, value, domain, '/', seconds);
                Debugger.info('Tracker()', 'Cookie "' + ctype + '" set with value ' + value);
            } else {
                Debugger.warn('Tracker()', 'Unable to set cookie. The cookie value was empty.');
            }
        }

        /**
         *
         */
        function getCookie(ctype)
        {
            if (!Utils.isDefined(config.get('cookie')[ctype])) {
                Debugger.info('Tracker()', 'Unable to retrieve cookie. Cookie "' + ctype + '" was not found.');
                return null;
            }
            var cookie = parseCookieValue(Utils.getCookie(config.get('cookie')[ctype].key));
            if (null === cookie) {
                Debugger.info('Tracker()', 'No value set for cookie type "' + ctype + '"');
                return cookie;
            }

            if (cookieRequiresId(ctype) && !Utils.isDefined(cookie.id)) {
                Debugger.warn('Tracker()', 'The value for cookie type "' + ctype + '" requires an id but is missing. Ignoring cookie.');
                return null;
            }
            return cookie;
        }

        function cookieRequiresId(ctype)
        {
            return ctype === 'visitor' || ctype === 'session' || ctype === 'customer';
        }

        /**
         *
         */
        function parseCookieValue(value)
        {
            if (null === value) {
                return value;
            }
            value = Utils.urlDecode(value);
            try {
                return JSON.parse(value);
            } catch (e) {
                Debugger.error('Tracker()', 'Failed to parse cookie JSON.');
                return null;
            }
        }

        function Config()
        {
            var values = {
                app: null,
                baseEndpoint: '/events',
                campaign: {
                    source: null,
                    medium: null,
                    name: null,
                    content: null,
                    keyword: null,
                },
                campaignKeys: {
                    source: 'utm_source',
                    medium: 'utm_medium',
                    name: 'utm_campaign',
                    content: 'utm_content',
                    keyword: 'utm_term'
                },
                cookie: {
                    visitor: {
                        key: '__sapience_v',
                        expires: 1051200,
                    },
                    session: {
                        key: '__sapience_s',
                        expires: 30
                    },
                    identity: {
                        key: '__sapience_i',
                        expires: 1051200
                    },
                    campaign: {
                        key: '__sapience_c',
                        expires: 259200
                    }
                },
                disabled: false,
                domainName: document.domain,
                endpoint: null,
                page: {
                    title: Utils.getPageTitle(),
                    type: null,
                    url: window.location.href,
                },
                referrer: Utils.getReferrer(),
                referringIdentityKey: 'sapience_ri',
                scrollSelector: null,
                trackerDomain: 'http://olytics.cygnus.com',
                useCookieDomain: false
            };

            function isValid()
            {
                return Utils.isString(values.endpoint) && Utils.isString(values.trackerDomain);
            }

            return {
                setDisabled: function(bit) {
                    if (!Utils.isDefined(bit)) {
                        Debugger.warn('Config()', 'Unable to disable/enable the tracker.');
                        return this;
                    }
                    bit = Boolean(bit);
                    var status = (bit) ? 'disabled' : 'enabled';
                    values.disabled = bit;
                    Debugger.info('Config()', 'The tracker is now ' + status + '.');
                },
                setDomainName: function(domain) {
                    if (!Utils.isString(domain)) {
                        Debugger.warn('Config()', 'Unable to set the domain name.');
                        return this;
                    }

                    values.domainName = domain;
                    values.useCookieDomain = values.domainName !== document.domain;
                    Debugger.info('Config()', 'Domain name "' + domain + '" set.');
                    return this;
                },
                setEndpoint: function(endpoint) {
                    if (!Utils.isString(endpoint)) {
                        Debugger.warn('Config()', 'Unable to set the endpoint.');
                        return this;
                    }
                    values.endpoint = endpoint;
                    Debugger.info('Config()', 'Endpoint "' + endpoint + '" set.');
                    return this;
                },
                setTrackerDomain: function(domain) {
                    if (!Utils.isString(domain)) {
                        Debugger.warn('Config()', 'Unable to set the tracker domain.');
                        return this;
                    }
                    values.trackerDomain = domain;
                    Debugger.info('Config()', 'Tracker domain "' + domain + '" set.');
                    return this;
                },
                setPage: function (title, url) {
                    if (Utils.isString(title)) {
                        values.page.title(title);
                        Debugger.info('Config()', 'Page title "' + title + '" set.');
                    } else {
                        Debugger.warn('Config()', 'Unable to set the page title.');
                    }

                    if (Utils.isString(url)) {
                        values.page.url(url);
                        Debugger.info('Config()', 'Page url "' + url + '" set.');
                    } else {
                        Debugger.warn('Config()', 'Unable to set the page url.');
                    }
                    return this;
                },
                setReferrer: function (url) {
                    if (!Utils.isString(url)) {
                        Debugger.warn('Config()', 'Unable to set the referrer.');
                        return this;
                    }
                    values.referrer(url);
                    Debugger.info('Config()', 'Referrer "' + url + '" set.');
                    return this;
                },
                setPageType: function (type) {
                    if (!Utils.isString(type)) {
                        Debugger.warn('Config()', 'Unable to set the page type.');
                        return this;
                    }
                    values.page.type = type;
                    Debugger.info('Config()', 'Page type "' + type + '" set.');
                    return this;
                },
                setApp: function (app) {
                    if (!Utils.isString(app)) {
                        Debugger.warn('Config()', 'Unable to set the app.');
                        return this;
                    }
                    values.app = app;
                    Debugger.info('Config()', 'App "' + app + '" set.');
                    return this;
                },
                setCookieName: function(ctype, name) {
                    if (!Utils.isString(ctype)) {
                        Debugger.warn('Config()', 'Unable to modify cookie name. The cookie type is invalid.');
                        return this;
                    }
                    if (!Utils.isString(name)) {
                        Debugger.warn('Config()', 'Unable to modify cookie name. No cookie name was provided.');
                        return this;
                    }

                    if (values.cookie.hasOwnProperty(ctype)) {
                        values.cookie[ctype].key = name;
                        Debugger.info('Config()', 'Cookie name "' + name + '" set for cookie type "' + ctype + '"');
                    } else {
                        Debugger.warn('Config()', 'Unable to modify cookie name. The cookie type "' + ctype + '" does not exist.');
                    }
                    return this;
                },
                setCookieExpires: function(ctype, minutes) {
                    if (!Utils.isString(ctype)) {
                        Debugger.warn('Config()', 'Unable to modify cookie expiration. The cookie type is invalid.');
                        return this;
                    }
                    if (!Utils.isNumber(minutes) || minutes < 0) {
                        Debugger.warn('Config()', 'Unable to modify cookie expiration. Cookie time must be set as non-negative minutes.');
                        return this;
                    }

                    if (values.cookie.hasOwnProperty(ctype)) {
                        values.cookie[ctype].expires = minutes;
                        Debugger.info('Config()', 'Cookie expiration of ' + minutes + ' minutes for type "' + ctype + '" set.');
                    } else {
                        Debugger.warn('Config()', 'Unable to modify cookie expiration. The cookie type "' + ctype + '" does not exist.');
                    }
                    return this;
                },
                setReferringIdentityKey: function (key) {
                    if (!Utils.isString(key)) {
                        Debugger.warn('Config()', 'Unable to set the referring identity.');
                        return this;
                    }
                    values.referringIdentityKey = key;
                    Debugger.info('Config()', 'Referring identity key "' + key + '" set.');
                    return this;
                },
                setCampaignKey: function (key, value) {
                    if (!Utils.isDefined(values.campaignKeys[key])) {
                        Debugger.warn('Config()', 'Unable to set the campaign key. The key "' + key + '" does not exist.');
                        return this;
                    }

                    if (!Utils.isString(value)) {
                        Debugger.warn('Config()', 'Unable to set the campaign key value');
                        return this;
                    }
                    values.campaignKeys[key] = value;
                    Debugger.info('Config()', 'Campaign key "' + key + '" now set to "' + value + '"');
                    return this;
                },
                setCampaignValue: function (key, value) {
                    if (!Utils.isDefined(values.campaign[key])) {
                        Debugger.warn('Config()', 'Unable to set the campaign value. The key "' + key + '" does not exist.');
                        return this;
                    }
                    if (!Utils.isString(value)) {
                        Debugger.warn('Config()', 'Unable to set the campaign value.');
                        return this;
                    }
                    values.campaign[key] = value;
                    Debugger.info('Config()', 'Campaign value "' + value + '" now set to "' + key + '"');
                    return this;
                },
                setScrollSelector: function (selector) {
                    if (!Utils.isString(selector)) {
                        Debugger.warn('Config()', 'Unable to set the scroll selector value.');
                        return this;
                    }
                    values.scrollSelector = selector;
                    Debugger.info('Config()', 'Scroll selector "' + selector + '" set.');
                    return this;
                },
                get: function(key) {
                    if (values.hasOwnProperty(key)) {
                        return values[key];
                    }
                    return null;
                },
                isValid: function(key) {
                    return isValid();
                }
            }
        }

        return {
            _debug: function(bit) {
                bit = Boolean(bit);
                if (true === bit) { Debugger.enable() } else { Debugger.disable() };
                return this;
            },
            _config: function() {
                if (!Utils.isDefined(arguments[0])) {
                    Debugger.warn('Unable to set config value(s). No config set method was defined.');
                    return this;
                }

                var args = [];
                for (var i = 0; i < arguments.length; i++)  {
                    args[i] = arguments[i];
                }

                var method = args.shift();
                if (!Utils.isFunction(config[method])) {
                    Debugger.warn('Unable to set config value(s). The method "' + method + '" is not a valid configuration method');
                    return this;
                }
                config[method].apply(config, args);
                return this;
            },
            _trackEvent: function(action, entity, relatedTo, data) {
                trackEvent(action, entity, relatedTo, data);
                return this;
            },
            _trackPageview: function() {
                trackPageview();
                return this;
            },
            _trackScroll: function (entity) {
                trackScroll(entity);
                return this;
            },
            _resendLastEvent: function(action) {
                resendLastEvent(action);
                return this;
            },
            config: config
        }
    }

    /**
     *
     */
    function Utils()
    {
        var
            urlEncode = window.encodeURIComponent,
            urlDecode = window.decodeURIComponent
        ;

        /**
         *
         */
        function Url(url)
        {
            this.value = url;
            this.element = document.createElement('a');
            this.element.href = url;

            this.getPath = function()
            {
                return this.element.pathname;
            }

            this.getHost = function()
            {
                return this.element.hostname;
            }

            this.getScheme = function()
            {
                var e = new RegExp('^([a-z]+):'),
                matches = e.exec(this.value);
                return matches ? matches[1] : null;
            }

            this.getQuery = function()
            {
                return this.element.search;
            }

            this.getQueryParam = function (key)
            {
                var
                    regex = new RegExp(key +'=([^&]+)'),
                    qs = this.getQuery()
                ;
                if (!qs) {
                    return null;
                }

                var results = qs.match(regex);
                return results ? urlDecode(results[1]) : null;
            }
        }

        return {
            isObject: function(property) {
                return typeof property === 'object';
            },

            isArray: function(obj) {
                return Object.prototype.toString.call(obj) === '[object Array]';
            },

            isFunction: function(property) {
                return typeof property === 'function';
            },

            isNumber: function(property) {
                return typeof property === 'number';
            },

            isDefined: function(property) {
                return typeof property !== 'undefined';
            },

            isString: function(property) {
                return typeof property === 'string' || property instanceof String;
            },
            rand: function() {
                return Math.floor(Math.random() * 9999999999) + ""
            },
            url: function(url) {
                return new Url(url);
            },
            getCookie: function(name) {
                var cookies = document.cookie.split(';');
                for (var i = 0; i < cookies.length; i++) {
                    var cookie = cookies[i].trim().split('=');
                    if (cookie[0] == name) {
                        return cookie[1];
                    }
                }
                return null;
            },
            setCookie: function(name, value, domain, path, seconds) {
                var d = new Date();
                d.setTime(d.getTime() + (seconds * 1000)); // Milliseconds

                var
                    expires = (seconds > 0) ? '; expires=' + d.toGMTString() : '',
                    domain = (domain) ? '; domain=' + domain : '',
                    path = '; path=' + path
                ;

                if (value) {
                    document.cookie = urlEncode(name) + '=' + urlEncode(value) + expires + domain + path;
                }
            },
            getReferrer: function() {
                var referrer = null;
                try {
                    referrer = window.top.document.referrer;
                } catch (e) {
                    if (window.parent) {
                        try {
                            referrer = window.parent.document.referrer;
                        } catch (e2) {
                            referrer = null;
                        }
                    }
                }
                if (referrer === null) {
                    referrer = document.referrer;
                }
                if (referrer === '') {
                    return null;
                }
                return referrer;
            },
            getPageTitle: function() {
                var elements = document.getElementsByTagName('title');
                if (elements && this.isDefined(elements[0])) {
                    return urlDecode(elements[0].text);
                }
                return null;
            },
            getTimezone: function() {
                var d = new Date();
                return d.getTimezoneOffset();
            },
            getPixelRatio: function() {
                return (new RegExp('Mac OS X.*Safari/')).test(navigator.userAgent) ? window.devicePixelRatio || 1 : 1;
            },
            getResolution: function() {
                var pixelRatio = this.getPixelRatio();

                return {
                    width: screen.width * pixelRatio,
                    height: screen.height * pixelRatio
                };
            },
            getWindowSize: function() {
                var pixelRatio = this.getPixelRatio();

                var w = Math.max(document.documentElement.clientWidth, window.innerWidth || 0) * pixelRatio;
                var h = Math.max(document.documentElement.clientHeight, window.innerHeight || 0) * pixelRatio;

                return {
                    width: w,
                    height: h
                };
            },
            urlEncode: urlEncode,
            urlDecode: urlDecode
        };
    }

    /**
     *
     */
    function Debugger()
    {
        init();

        var enabled = false;

        this.enable = function() {
            enabled = true;
            return this;
        }

        this.disable = function() {
            enabled = false;
            return this;
        }

        this.log = function() {
            dispatch('log', arguments);
            return this;
        }

        this.info = function() {
            dispatch('info', arguments);
            return this;
        }

        this.warn = function() {
            dispatch('warn', arguments);
            return this;
        }

        this.error = function() {
            dispatch('error', arguments);
            return this;
        }

        /**
         *
         */
        function dispatch(method, arguments)
        {
            if (true === enabled) {
                var args = ['SAPIENCE DEBUGGER:'];
                for (var i = 0; i < arguments.length; i++)  {
                    var n = i + 1;
                    args[n] = arguments[i];
                }
                console[method].apply(console, args);
            }
        }

        /**
         *
         */
        function init()
        {
            if (typeof console === 'undefined') {
                console = {};
            }
            var methods = ['log', 'info', 'warn', 'error'];
            for (var key in methods) {
                var method = methods[key];
                if (typeof console[method] === 'undefined') {
                    console[method] = function() {};
                }
            }
        }
    }

    /**
     *
     */
    function Proxy()
    {
        return {
            push: apply
        };
    }

    function Request(requestObj, primaryUrl)
    {
        var
            requestObj = requestObj,
            primaryUrl = primaryUrl,
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

                    var s = document.createElement('script');
                    s.type = 'text/javascript';
                    s.src = url;

                    window[callback] = function(data) {
                        document.getElementsByTagName('head')[0].removeChild(s);
                        delete window[callback];
                    };

                    document.getElementsByTagName('head')[0].appendChild(s);
                }
            };

        this.send = function()
        {
            switch (requestType) {
                case 'xhr':
                    var body = JSON.stringify(requestObj);
                    Debugger.info('Request()', 'Sending the request object via XHR.');
                    request.xhr('POST', primaryUrl, body);
                    break;
                case 'xdr':
                    var body = JSON.stringify(requestObj);
                    Debugger.info('Request()', 'Sending the request object via XDR.');
                    request.xdr('POST', primaryUrl, body);
                    break;
                case 'jsonp':
                    var encoded = encodeWrapper(Utils.Base64().encode(JSON.stringify(requestObj)));
                    var url = primaryUrl + '?enc=' + encoded;
                    Debugger.info('Request()', 'Sending the request object via JSONP.');
                    request.jsonp(url);
                default:
                    break;
            }
        }

        function detectRequestSupport()
        {
            if ((Utils.isObject(XMLHttpRequest) || Utils.isFunction(XMLHttpRequest)) && 'withCredentials' in new XMLHttpRequest()) {
                return 'xhr';
            } else {
                if (typeof XDomainRequest !== 'undefined') {
                    return 'xdr';
                } else {
                    return 'jsonp';
                }
            }
        }
    }

    /**
     *
     */
    function Event(action, entity, relatedEntities, data, createdAt)
    {
        this.setAction = function(value)
        {
            this.action = (Utils.isString(value)) ? value.toLowerCase() : '';
        }

        this.setData = function(value)
        {
            this.data = (Utils.isObject(value)) ? value : {};
        }

        this.setCreatedAt = function(value)
        {
            var d = new Date();
            this.createdAt = (value instanceof Date) ? value.toGMTString() : d.toGMTString();
        }

        this.setRelatedEntities = function(value)
        {
            this.relatedEntities = [];
            if (Utils.isArray(value)) {
                for (var n = 0; n < value.length; n++) {
                    if (value[n] instanceof Entity) {
                        if (value[n].isValid()) this.relatedEntities.push(value[n]);
                    } else if (Utils.isObject(value)) {
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
            } else if (Utils.isObject(value)) {
                var e = new Entity();
                this.entity = e.hydrate(value);
            } else {
                this.entity = null;
            }
        }

        this.isValid = function()
        {
            if (this.action.length == 0) {
                Debugger.error('Event()', 'The model is invalid and will not be used. The action is empty.');
                return false;
            }
            if (this.entity === null || this.entity.isValid() === false) {
                Debugger.error('Event()', 'The model is invalid and will not be used. The entity is empty or invalid.');
                return false;
            }
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

    /**
     *
     */
    function Entity(type, clientId, keyValues, relatedTo)
    {
        this.setType = function(value)
        {
            this.type = (Utils.isString(value)) ? value.toLowerCase() : '';
        }

        this.setClientId = function(value)
        {
            this.clientId = (Utils.isDefined(value)) ? value : null;
        }

        this.setKeyValues = function(value)
        {
            this.keyValues = (Utils.isObject(value)) ? value : {};
        }

        this.setRelatedTo = function(value)
        {
            this.relatedTo = [];
            if (Utils.isObject(value)) {
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
            if (Utils.isObject(entity)) {
                if (Utils.isDefined(entity.type)) this.setType(entity.type);
                if (Utils.isDefined(entity.clientId)) this.setClientId(entity.clientId);
                if (Utils.isDefined(entity.keyValues)) this.setKeyValues(entity.keyValues);
                if (Utils.isDefined(entity.relatedTo)) this.setRelatedTo(entity.relatedTo);
            }
            return this;
        }

        this.isValid = function()
        {
            if (this.type.length == 0) {
                Debugger.error('Entity()', 'The model is invalid and will not be used. The type is empty.');
                return false;
            }
            if (this.clientId === null) {
                Debugger.error('Entity()', 'The model is invalid and will not be used. The clientId is empty.');
                return false;
            }
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

    /**
     *
     */
    function RelatedEntity(type, clientId, keyValues, relFields)
    {

        this.setType = function(value)
        {
            this.type = (Utils.isString(value)) ? value.toLowerCase() : '';
        }

        this.setClientId = function(value)
        {
            this.clientId = (Utils.isDefined(value)) ? value : null;
        }

        this.setKeyValues = function(value)
        {
            this.keyValues = (Utils.isObject(value)) ? value : {};
        }

        this.setRelFields = function(value)
        {
            this.relFields = (Utils.isObject(value)) ? value : {};
        }

        this.hydrate = function(entity)
        {
            if (Utils.isObject(entity)) {
                if (Utils.isDefined(entity.type)) this.setType(entity.type);
                if (Utils.isDefined(entity.clientId)) this.setClientId(entity.clientId);
                if (Utils.isDefined(entity.keyValues)) this.setKeyValues(entity.keyValues);
                if (Utils.isDefined(entity.relFields)) this.setRelFields(entity.relFields);
            }
            return this;
        }

        this.isValid = function()
        {
            if (this.type.length == 0) {
                Debugger.error('RelatedEntity()', 'The model is invalid and will not be used. The type is empty.');
                return false;
            }
            if (this.clientId === null) {
                Debugger.error('RelatedEntity()', 'The model is invalid and will not be used. The clientId is empty.');
                return false;
            }
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

    /**
     *
     */
    function Base64()
    {
        return {

            _keyStr : "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=",

            encode : function (input) {
                var output = "";
                var chr1, chr2, chr3, enc1, enc2, enc3, enc4;
                var i = 0;

                input = this._utf8_encode(input);

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
                output = this._utf8_decode(output);
                return output;
            },

            _utf8_encode : function (string) {
                string = string.replace(/\r\n/g,"\n");
                var utftext = "";

                for (var n = 0; n < string.length; n++) {

                    var c = string.charCodeAt(n);

                    if (c < 128) {
                        utftext += String.fromCharCode(c);
                    } else if((c > 127) && (c < 2048)) {
                        utftext += String.fromCharCode((c >> 6) | 192);
                        utftext += String.fromCharCode((c & 63) | 128);
                    } else {
                        utftext += String.fromCharCode((c >> 12) | 224);
                        utftext += String.fromCharCode(((c >> 6) & 63) | 128);
                        utftext += String.fromCharCode((c & 63) | 128);
                    }

                }
                return utftext;
            },

            _utf8_decode : function (utftext) {
                var string = "";
                var i = 0;
                var c = c1 = c2 = 0;

                while ( i < utftext.length ) {

                    c = utftext.charCodeAt(i);

                    if (c < 128) {
                        string += String.fromCharCode(c);
                        i++;
                    } else if((c > 191) && (c < 224)) {
                        c2 = utftext.charCodeAt(i+1);
                        string += String.fromCharCode(((c & 31) << 6) | (c2 & 63));
                        i += 2;
                    } else {
                        c2 = utftext.charCodeAt(i+1);
                        c3 = utftext.charCodeAt(i+2);
                        string += String.fromCharCode(((c & 15) << 12) | ((c2 & 63) << 6) | (c3 & 63));
                        i += 3;
                    }
                }
                return string;
            }
        };
    }

    /**
     *
     */
    function init()
    {
        // Fire debug enable/disable first
        for (var i = 0; i < _sapient.length; i++) {
            if (_sapient[i][0] === '_debug') {
                apply(_sapient[i]);
                _sapient.splice(i, 1);
            }
        }

        // Fire all config options next
        for (var i = 0; i < _sapient.length; i++) {
            if (_sapient[i][0] === '_config') {
                apply(_sapient[i]);
                _sapient.splice(i, 1);
            }
        }

        // Fire remaining actions
        for (var i = 0; i < _sapient.length; i++) {
            if (_sapient[i]) {
                apply(_sapient[i]);
            }
        }
    }

    /**
     *
     */
    function apply()
    {
        var i, f, parameterArray;

        for (var i = 0; i < arguments.length; i++)  {
            parameterArray = arguments[i];
            f = parameterArray.shift();

            if (Utils.isString(f)) {
                if (Utils.isDefined(Tracker[f])) {
                    Tracker[f].apply(Tracker, parameterArray);
                } else {
                    Debugger.error('Sapience()', 'Unable to apply method "' + f + '" to Tracker object. The function does not exist.');
                }
            } else {
                f.apply(Tracker, parameterArray);
            }
        }
    }

    return {
        getTracker: function() {
            return Tracker;
        },
        getDebugger: function() {
            return Debugger;
        },
        getUtils: function() {
            return Utils;
        }
    };

}());
