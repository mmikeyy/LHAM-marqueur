"use strict";
/**
 * Created by micra_000 on 2016-04-15.
 */
/////<reference path="../../typings/rx.all.d.ts"/>
Object.defineProperty(exports, "__esModule", { value: true });
var core_1 = require("@angular/core");
var translate_service_1 = require("@ngx-translate/core/src/translate.service");
var myTranslationLoader_1 = require("./myTranslationLoader");
// @Directive({})
var LanguageService = (function () {
    function LanguageService(http) {
        console.log('Construction service');
        LanguageService.init();
        LanguageService.http = http;
    }
    LanguageService.init = function () {
        if (LanguageService.changeEmitter === undefined) {
            LanguageService.changeEmitter = new core_1.EventEmitter(LanguageService.asyncEventEmitter);
        }
    };
    LanguageService.setLang = function (lang) {
        LanguageService.init();
        var currentLang = LanguageService.currentLang;
        LanguageService.currentLang = LanguageService.langs.indexOf(lang) > -1 ? lang : LanguageService.defaultLang;
        if (LanguageService.currentLang !== currentLang) {
            LanguageService.emitChange();
        }
        return LanguageService.currentLang;
    };
    LanguageService.changeLang = function () {
        var nouvIndex = LanguageService.langs.indexOf(LanguageService.currentLang) + 1;
        nouvIndex = nouvIndex >= LanguageService.langs.length ? 0 : nouvIndex;
        LanguageService.setLang(LanguageService.langs[nouvIndex]);
    };
    LanguageService.setupService = function (context, 
        // loader: MyTranslationLoader,
        translateService, newService) {
        var serv;
        if (newService) {
            serv = new translate_service_1.TranslateService(translateService.store, translateService.currentLoader, translateService.compiler, translateService.parser, translateService.missingTranslationHandler); // translateService.currentLoader, null
        }
        else {
            // console.log('service existe ' + context);
            serv = translateService;
        }
        LanguageService.init();
        LanguageService.injectLoader(context, serv);
        serv.setDefaultLang('fr');
        serv.use(LanguageService.currentLang);
        // console.log('......... using lang ' + LanguageService.currentLang);
        return serv;
    };
    LanguageService.injectLoader = function (context, translateService) {
        new myTranslationLoader_1.MyTranslationLoader(translateService, context);
    };
    LanguageService.emitChange = function () {
        LanguageService.changeEmitter.emit(LanguageService.currentLang);
    };
    LanguageService.langs = ['fr', 'en'];
    LanguageService.defaultLang = 'fr';
    LanguageService.currentLang = 'fr';
    LanguageService.nb = 0;
    LanguageService.asyncEventEmitter = false; // set false for testing
    LanguageService.debug = true;
    return LanguageService;
}());
exports.LanguageService = LanguageService;
