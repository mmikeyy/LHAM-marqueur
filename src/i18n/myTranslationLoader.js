"use strict";
var __decorate = (this && this.__decorate) || function (decorators, target, key, desc) {
    var c = arguments.length, r = c < 3 ? target : desc === null ? desc = Object.getOwnPropertyDescriptor(target, key) : desc, d;
    if (typeof Reflect === "object" && typeof Reflect.decorate === "function") r = Reflect.decorate(decorators, target, key, desc);
    else for (var i = decorators.length - 1; i >= 0; i--) if (d = decorators[i]) r = (c < 3 ? d(r) : c > 3 ? d(target, key, r) : d(target, key)) || r;
    return c > 3 && r && Object.defineProperty(target, key, r), r;
};
Object.defineProperty(exports, "__esModule", { value: true });
var core_1 = require("@angular/core");
// import {TranslateLoader} from 'ng2-translate/ng2-translate';
// import {TranslateService} from '@ngx-translate/core';
var language_service_1 = require("./language.service");
var Rx_1 = require("rxjs/Rx");
var MyTranslationLoader = (function () {
    function MyTranslationLoader(trans, context) {
        this.trans = trans;
        this.context = context;
        this.error = false;
        this.setup();
    }
    MyTranslationLoader_1 = MyTranslationLoader;
    MyTranslationLoader.getLang = function () {
        return language_service_1.LanguageService.currentLang;
    };
    MyTranslationLoader.indexServ = function (serv) {
        return MyTranslationLoader_1.listeServices.indexOf(serv);
    };
    MyTranslationLoader.registerServ = function (serv) {
        if (MyTranslationLoader_1.indexServ(serv) === -1) {
            MyTranslationLoader_1.listeServices.push(serv);
        }
    };
    MyTranslationLoader.prototype.setup = function (trans, context) {
        var _this = this;
        language_service_1.LanguageService.init();
        this.trans = trans || this.trans || null;
        if (context) {
            this.context = context;
        }
        if (this.trans && this.context) {
            MyTranslationLoader_1.registerServ(this.trans);
            // console.log('CCCCCCCCCCCCCCCCreate loader', this.context, 'no ' + MyTranslationLoader.indexServ(this.trans));
            this.reqContext = require.context('../assets/i18n/', true, /.*\.json$/);
            // let req = this.reqContext.keys();
            this.trans.currentLoader = this;
            this.langObserver = language_service_1.LanguageService.changeEmitter.subscribe(function (lang) {
                _this.trans.use(lang);
            });
            language_service_1.LanguageService.emitChange();
        }
    };
    MyTranslationLoader.prototype.translationFileName = function () {
        var name = "./" + MyTranslationLoader_1.getLang() + "/" + (this.context || 'default') + ".json";
        return name;
    };
    MyTranslationLoader.prototype.isTranslatable = function (str) {
        if (this.context === 'default') {
            return this.isTranslatableContext(str);
        }
        return this.isTranslatableContext(str, this.context) || this.isTranslatableContext(str);
    };
    MyTranslationLoader.prototype.isTranslatableContext = function (str, context) {
        if (context === void 0) { context = 'default'; }
        var def = MyTranslationLoader_1.translations[context];
        return !!(def && def[str]);
    };
    MyTranslationLoader.prototype.getTranslation = function (lang) {
        // let context = this.reqContext(tag);
        if (!this.translations) {
            var tag = this.translationFileName();
            this.translations = this.reqContext(tag);
        }
        // console.log('.get translation.......................... context = ', this.context);
        // console.log('Translations for ' + this.context, this.translations);
        return Rx_1.Observable.of(this.translations);
    };
    MyTranslationLoader.prototype.log = function (a, b, c) {
        if (language_service_1.LanguageService.debug) {
            console.log.apply(console, arguments);
            console.trace();
        }
    };
    MyTranslationLoader.observables = {};
    MyTranslationLoader.translations = {};
    MyTranslationLoader.listeServices = [];
    MyTranslationLoader = MyTranslationLoader_1 = __decorate([
        core_1.Injectable()
    ], MyTranslationLoader);
    return MyTranslationLoader;
    var MyTranslationLoader_1;
}());
exports.MyTranslationLoader = MyTranslationLoader;
