var __decorate = (this && this.__decorate) || function (decorators, target, key, desc) {
    var c = arguments.length, r = c < 3 ? target : desc === null ? desc = Object.getOwnPropertyDescriptor(target, key) : desc, d;
    if (typeof Reflect === "object" && typeof Reflect.decorate === "function") r = Reflect.decorate(decorators, target, key, desc);
    else for (var i = decorators.length - 1; i >= 0; i--) if (d = decorators[i]) r = (c < 3 ? d(r) : c > 3 ? d(target, key, r) : d(target, key)) || r;
    return c > 3 && r && Object.defineProperty(target, key, r), r;
};
var __param = (this && this.__param) || function (paramIndex, decorator) {
    return function (target, key) { decorator(target, key, paramIndex); }
};
import { Inject, Injectable } from '@angular/core';
import { LanguageService } from './language.service';
import { Observable } from 'rxjs/Rx';
import { CONTEXT } from './translation.context';
export var createContextualLoader = function (context) {
    return new ContextualTranslationLoader(context);
};
export var ContextualTranslationLoader = (function () {
    function ContextualTranslationLoader(translationContext) {
        this.translationContext = translationContext;
        this.error = false;
        this.context = this.translationContext.context;
        /// console.log('CCCCCCCCreating contextualtranslationloader', this.context);
        this.reqContext = require.context('../assets/i18n/', true, /.*\.json$/);
        LanguageService.emitChange();
    }
    ContextualTranslationLoader.getLang = function () {
        return LanguageService.currentLang;
    };
    ContextualTranslationLoader.prototype.translationFileName = function () {
        var name = "./" + ContextualTranslationLoader.getLang() + "/" + (this.context || 'default') + ".json";
        return name;
    };
    ContextualTranslationLoader.prototype.isTranslatable = function (str) {
        if (this.context === 'default') {
            return this.isTranslatableContext(str);
        }
        return this.isTranslatableContext(str, this.context) || this.isTranslatableContext(str);
    };
    ContextualTranslationLoader.prototype.isTranslatableContext = function (str, context) {
        if (context === void 0) { context = 'default'; }
        var def = ContextualTranslationLoader.translations[context];
        return !!(def && def[str]);
    };
    ContextualTranslationLoader.prototype.getTranslation = function (lang) {
        // let context = this.reqContext(tag);
        // console.log('....................................... context = ', context);
        if (!this.translations) {
            var tag = this.translationFileName();
            this.translations = this.reqContext(tag);
            console.log('GGGGGGGGGGGGetting translations for ', this.context, this.translations);
        }
        return Observable.of(this.translations);
    };
    ContextualTranslationLoader.prototype.log = function (a, b, c) {
        if (LanguageService.debug) {
            console.log.apply(console, arguments);
            console.trace();
        }
    };
    ContextualTranslationLoader.observables = {};
    ContextualTranslationLoader.translations = {};
    ContextualTranslationLoader = __decorate([
        Injectable(),
        __param(0, Inject(CONTEXT))
    ], ContextualTranslationLoader);
    return ContextualTranslationLoader;
}());
