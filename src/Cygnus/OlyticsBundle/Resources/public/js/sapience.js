(function(Sapience, undefined) {

    'use strict';

    setCompatibility();

    // Private Properties
    var Utils           = new Utils();
    var Debugger        = new Debugger();
    var Config          = new Config();
    var CookieManager   = new CookieManager();
    var CampaignManager = new CampaignManager();
    var Tracker         = new Tracker();

    // Public Properties
    Sapience.Config             = Config;
    Sapience.CampaignManager    = CampaignManager;
    Sapience.Tracker            = Tracker;
    Sapience.CookieManager      = CookieManager;

    // Public Functions
    Sapience.setDebug = function(bit) {
        bit = Boolean(bit);
        if (true === bit) { Debugger.enable() } else { Debugger.disable() };
        return Sapience;
    }

    // Private Functions

    function Tracker()
    {
        this.Focus  = new Focus();
        this.Unload = new Unload();
        this.Scroll = new Scroll(Config.get('scrollSelector'));

        var
            appendIdentity = false,
            env = {
                res: Utils.getResolution(),
                tz: Utils.getTimezone(),
                windowRes: Utils.getWindowSize()
            },
            visitor, session, identity, campaign
        ;

        this.setIdentity = function (id) {
            setIdentity({id: id});
            return this;
        }

        this.send = function(action, entity, relatedTo, data)
        {
            if (Config.get('disabled')) {
                Debugger.error('Tracker()', 'The tracker is currently disabled. No events will fire.');
                return this;
            }

            if (!Config.isValid()) {
                Debugger.error('Tracker()', 'The tracker configuration is invald. No events will fire.');
                return this;
            }

            var e = new Event(action, entity, relatedTo, data);
            if (e.isValid()) {
                init();
                var request = new Request(createRequestObject(e), getTrackerUrl());
                request.send();
            }
            return this;
        }

        this.clearBoundEntities = function()
        {
            Focus.clear();
            Scroll.clear();
            Unload.clear();
            return this;
        }

        function init()
        {
            visitor     = CookieManager.get('visitor');
            session     = CookieManager.get('session');
            identity    = CookieManager.get('identity');
            campaign    = CampaignManager.get();
            refreshCookies();
        }

        function getTrackerUrl()
        {
            return Config.get('trackerDomain') + Config.get('baseEndpoint') + Config.get('endpoint');
        }

        function createRequestObject(e)
        {
            var r = {
                app: Config.get('app'),
                appendCustomer: appendIdentity,
                event: e,
                session: session
            };

            r.session.campaign = campaign || null;

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
                } else {
                    s = session;
                }

                if (hasCampaign()) {
                    if (CampaignManager.hasCookie() && !CampaignManager.match(campaign, CampaignManager.getCampaignFromCookie())) {
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

            var identityId = hasIdentity() ? identity.id : null;
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
                createdAt: d.toGMTString()
            }
            Debugger.info('Tracker()', 'Created a new session with id ' + session.id);
            return session;
        }

        function setVisitor(value)
        {
            visitor = value;
            CookieManager.set('visitor', value);
        }

        function setSession(value)
        {
            session = value;
            CookieManager.set('session', value);
        }

        function setIdentity(value)
        {
            identity = value;
            CookieManager.set('identity', value);
        }

        function setCampaign(value)
        {
            campaign = value;
            CookieManager.set('campaign', value);
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

        function Unload()
        {
            var bound = [];
            var initialized = false;

            this.bind = function(entity) {
                init();
                bound.push(entity);
                Debugger.info('Unload()', 'Bound entity to window unload.', entity);
                return this;
            }

            this.clear = function() {
                bound = [];
                Debugger.info('Unload()', 'All bound entities have been cleared.');
                return this;
            }

            function init()
            {
                if (true === initialized) {
                    return;
                }
                window.addEventListener("unload", function (e) {
                    sendEvents();
                });
                initialized = true;
            }

            function sendEvents()
            {
                for (var i = 0; i < bound.length; i++) {
                    Tracker.send('unload', bound[i]);
                }
            }
        }

        function Focus()
        {
            var bound = [];
            var interval = 2000;
            var initialized = false;

            this.bind = function(entity) {
                init();
                bound.push(entity);
                Debugger.info('Focus()', 'Bound entity to window focus.', entity);
                return this;
            }

            this.clear = function() {
                bound = [];
                Debugger.info('Focus()', 'All bound entities have been cleared.');
                return this;
            }

            function init()
            {
                if (true === initialized) {
                    return;
                }

                var timer = 0;
                var hadFocus = document.hasFocus();

                var time = {
                    focused: 0,
                    blurred: 0,
                    thisDuration: 0,
                };

                setInterval(function() {

                    var seconds = interval / 1000;
                    var action, eventData;

                    timer += seconds;
                    if (hadFocus !== document.hasFocus()) {
                        // Focus change
                        if (hadFocus) {
                            // Lost focus
                            time.focused += timer;
                            action = 'focusout';
                        } else {
                            // Gained focus
                            time.blurred += timer;
                            action = 'focusin';
                            eventData = {idleSeconds: timer};
                        }
                        time.thisDuration = timer;
                        timer = 0;

                        var type = (hadFocus) ? 'lost focus' : 'gained focus';
                        Debugger.info('Focus()', 'The browser window has ' + type + '.', time);
                        hadFocus = document.hasFocus();

                        sendEvents(action, eventData);

                    }
                }, interval);

                initialized = true;
            }

            function sendEvents(action, data)
            {
                for (var i = 0; i < bound.length; i++) {
                    Tracker.send(action, bound[i], undefined, data);
                }
            }
        }

        function Scroll(selector)
        {
            var selector = selector;
            var bound = [];
            var delta = 5;
            var initialized = false;

            this.bind = function(entity) {
                init();
                bound.push(entity);
                Debugger.info('Scroll()', 'Bound entity to scroll.', entity);
                return this;
            }

            this.clear = function() {
                Debugger.info('Scroll()', 'All bound entities have been cleared.');
                bound = [];
                return this;
            }

            function init()
            {
                if (!hasSupport()) {
                    return this;
                }

                if (true === initialized) {
                    return this;
                }

                window.jQuery(document).ready(function() {
                    setTimeout(function () {
                        var element = window.jQuery(selector);
                        var didScroll = false;
                        var lastScrollTop = 0;

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

                            var vh = Utils.getViewPort().height;
                            var st = element.scrollTop();
                            var sb = st + vh;

                            // Make sure they scroll more than delta
                            if (Math.abs(lastScrollTop - st) <= delta) {
                                return;
                            }

                            var currentBreak = Math.round(sb / vh);
                            var direction = st > lastScrollTop ? 'down' : 'up';

                            if (currentBreak !== lastBreak) {
                                var percent = Math.round((sb / window.jQuery(document).height()) * 100);
                                Debugger.info('Scroll()', 'Scroll breakpoint ' + currentBreak + ' reached. Direction: ' + direction + '. Percent: ' + percent);
                                sendEvents(currentBreak, direction, percent);
                                lastBreak = currentBreak;
                            }
                            lastScrollTop = st;
                        }
                    }, 250);
                });
                initialized = true;
            }

            function sendEvents(breakpoint, direction, percent)
            {
                var data = {
                    breakpoint: breakpoint,
                    percent: percent,
                    direction: direction
                };
                for (var i = 0; i < bound.length; i++) {
                    Tracker.send('scroll', bound[i], undefined, data);
                }
            }

            function hasSupport()
            {
                if (!Utils.isDefined(window.jQuery)) {
                    Debugger.warn('Scroll()', 'jQuery must be loaded to enable scroll tracking. Tracking disabled.');
                    return false;
                }

                var required = '1.4.3';
                if (versionIsAtLeast(required)) {
                    return true;
                }

                Debugger.warn('Scroll()', 'jQuery must be at least version ' + required + '. Tracking disabled.');
                return false;
            }

            function versionIsAtLeast(requested)
            {
                var
                    requested = extractVersion(requested),
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
    }

    function CampaignManager()
    {
        this.get = function ()
        {
            if (this.hasQueryString()) {
                return this.getCampaignFromQuery();
            }
            if (this.hasCookie()) {
                return this.getCampaignFromCookie();
            }
            if (this.hasConfigured()) {
                return this.getCampaignFromConfig();
            }
            return null;
        }

        this.getCampaignFromQuery = function()
        {
            var requestCampaign = {}, keys = getCampaignKeys();
            for (var key in keys) {
                requestCampaign[key] = Utils.url(window.location.href).getQueryParam(keys[key]);
            }

            if (campaignObjValid(requestCampaign)) {
                return cleanCampaign(requestCampaign);
            }
            return null;
        }

        this.getCampaignFromCookie = function()
        {
            var cookie = CookieManager.get('campaign');
            if (cookie === null) return null;
            if (campaignObjValid(cookie)) {
                return cleanCampaign(cookie);
            }
            return null;
        }

        this.getCampaignFromConfig = function()
        {
            var campaign = Config.get('campaign');
            if (campaignObjValid(campaign)) {
                return cleanCampaign(campaign);
            }
            return null;
        }

        this.hasQueryString = function()
        {
            return null !== this.getCampaignFromQuery();
        }

        this.hasCookie = function()
        {
            return null !== this.getCampaignFromCookie();
        }

        this.hasConfigured = function()
        {
            return null !== this.getCampaignFromConfig();
        }

        this.match = function(c1, c2)
        {
            var keys = getCampaignKeys();
            for (var key in keys) {
                if (!c1.hasOwnProperty(key)) {
                    continue;
                }
                if (c1[key] !== c2[key]) {
                    return false;
                }
            }
            return true;
        }

        function getCampaignKeys()
        {
            return Config.get('campaignKeys');
        }

        function campaignObjValid(c)
        {
            var requiredKeys = ['source', 'medium', 'name'];
            var propFound = false;
            for (var i = 0; i < requiredKeys.length; i++) {
                var key = requiredKeys[i];

                if (!c.hasOwnProperty(key)) {
                    continue;
                }
                propFound = true;

                if (!c[key]) {
                    return false;
                }
            }
            return propFound;
        }

        function cleanCampaign(c)
        {
            var keys = getCampaignKeys();
            var cleaned = {};
            for (var key in keys) {
                if (!c.hasOwnProperty(key)) {
                    continue;
                }
                cleaned[key] = c[key] || null;
            }
            return cleaned;
        }
    }

    function CookieManager()
    {
        /**
         *
         */
        this.set = function (ctype, value)
        {
            var cookie = Config.get('cookie');

            if (false === Utils.isDefined(cookie[ctype])) {
                Debugger.warn('CookieManager()', 'Unable to set cookie. Cookie type "' + ctype + '" does not exist.');
                return;
            }
            var
                name = cookie[ctype].key,
                value = JSON.stringify(value),
                seconds = cookie[ctype].expires * 60, // Config is in minutes, convert to seconds
                domain = (Config.get('useCookieDomain')) ? Config.get('domainName') : null
            ;
            if (value) {
                Utils.setCookie(name, value, domain, '/', seconds);
                Debugger.info('CookieManager()', 'Cookie "' + ctype + '" set with value ' + value);
            } else {
                Debugger.warn('CookieManager()', 'Unable to set cookie. The cookie value was empty.');
            }
        }

        /**
         *
         */
        this.get = function (ctype)
        {
            if (!Utils.isDefined(Config.get('cookie')[ctype])) {
                Debugger.info('CookieManager()', 'Unable to retrieve cookie. Cookie "' + ctype + '" was not found.');
                return null;
            }
            var cookie = parseCookieValue(Utils.getCookie(Config.get('cookie')[ctype].key));
            if (null === cookie) {
                Debugger.info('CookieManager()', 'No value set for cookie type "' + ctype + '"');
                return cookie;
            }

            if (cookieRequiresId(ctype) && !Utils.isDefined(cookie.id)) {
                Debugger.warn('CookieManager()', 'The value for cookie type "' + ctype + '" requires an id but is missing. Ignoring cookie.');
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
                Debugger.error('CookieManager()', 'Failed to parse cookie JSON.');
                return null;
            }
        }
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
                    var encoded = Utils.urlEncode(Utils.Base64().encode(JSON.stringify(requestObj)));
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
                influencer: null,
            },
            campaignKeys: {
                source: 'utm_source',
                medium: 'utm_medium',
                name: 'utm_campaign',
                content: 'utm_content',
                keyword: 'utm_term',
                influencer: 'utm_influencer'
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
            scrollSelector: window,
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
                return this;
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
                if (!Utils.isDefined(selector)) {
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
            getViewPort: function() {
                var dim = {
                    width: 0,
                    height: 0
                };
                if (this.isDefined(window.innerHeight)) {
                    dim.width = window.innerWidth;
                    dim.height = window.innerHeight;
                }
                return dim;
            },
            urlEncode: urlEncode,
            urlDecode: urlDecode
        };
    }

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
        function dispatch(method, passed)
        {
            if (true === enabled) {
                var args = ['SAPIENCE DEBUGGER:'];
                for (var i = 0; i < passed.length; i++)  {
                    var n = i + 1;
                    args[n] = passed[i];
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
            for (var i = 0; i < methods.length; i++) {
                var method = methods[i];
                if (typeof console[method] === 'undefined') {
                    console[method] = function() {};
                }
            }
        }
    }

    /**
     * Sets forward compatibility for browsers that lack support of 'built-in' functions.
     *
     */
    function setCompatibility()
    {
        if (typeof String.prototype.trim !== 'function') {
            String.prototype.trim = function() {
                return this.replace(/^\s+|\s+$/g, '');
            }
        }

        if (typeof document.hasFocus !== 'function') {
            // Opera lacks hasFocus support. Simply default to true.
            document.hasFocus = function() {
                return true;
            }
        }
    }

})(window.Sapience = window.Sapience || {});//, jQuery);
