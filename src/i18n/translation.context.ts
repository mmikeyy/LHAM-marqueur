import { InjectionToken } from '@angular/core';

export interface TranslationContext {
  context: string;
}

export let CONTEXT = new InjectionToken<TranslationContext>('i18n.context');

