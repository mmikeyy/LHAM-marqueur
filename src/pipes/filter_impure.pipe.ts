import { Pipe, PipeTransform } from '@angular/core';
import * as _ from 'lodash';
import { List, Map } from 'immutable';

@Pipe({
  name: 'myImpureFilter',
  pure: false
})
export class FilterImpurePipe implements PipeTransform {
  transform(val, cond?) {
    if (Map.isMap(val) || List.isList(val)) {
      return val.filter(cond);
    }
    if (!_.isArray(val)) {
      return val;
    }
    if (!cond) {
      return val;
    }

    return _.filter(val, cond);
  }
}
