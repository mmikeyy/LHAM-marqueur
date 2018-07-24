import { Component, EventEmitter, OnInit, OnDestroy } from '@angular/core';
import { Subscription } from 'rxjs';
import { TranslateService } from '@ngx-translate/core';
import { NgRedux, select } from '@angular-redux/store';

import * as _ from 'lodash';
import * as R from 'ramda';
import { MatchsActions } from '../../store/matchs/matchs.actions';
import * as moment from 'moment';
import { IAppState } from '../../store/store';

interface Match {
  id: string;
  eq1: string;
  eq2: string;
  date: string;
  debut: string;
  lieu: string;
  marqueur: string;
  marqueur_confirme: boolean;
  id_classe1: string;
  id_classe2: string;
  cl1: string;
  cl2: string;
  cl12: string;
}

@Component(
  {
    selector: '',
    templateUrl: './matchsFuturs.html'
  }
)
export class MatchsFuturs implements OnInit, OnDestroy {

  public subs: Subscription[] = [];

  public liste: Match[] = [];

  @select(['matchs']) $matchs;
  public nb = 0;
  public refresher;

  constructor(
    public store: NgRedux<IAppState>,
    public trServ: TranslateService,
    public matchsAct: MatchsActions
  ) {

  }

  ngOnInit() {
    let state = this.store.getState();
    this.subs.push(
      this.$matchs.subscribe(data => {

        if (this.refresher) {
          this.refresher.complete();
          this.refresher = null;
        }
        let equipes = data.get('equipes');
        let lieux = data.get('lieux');
        let classes = data.get('classes');

        this.liste = data.get('liste')
          .map(item => {
            let idCl1 = item.get('id_classe1');
            let idCl2 = item.get('id_classe2');
            let cl1 = classes.getIn([(idCl1 !== idCl2 ? idCl1 : null), 'classe']);
            let cl2 = classes.getIn([(idCl1 !== idCl2 ? idCl2 : null), 'classe']);
            let cl12 = classes.getIn([(idCl1 === idCl2 ? idCl1 : null), 'classe']);
            return {
              id: item.get('id'),
              eq1: equipes.getIn([item.get('id_equipe1'), 'nom'], '?'),
              eq2: equipes.getIn([item.get('id_equipe2'), 'nom'], '?'),
              date: item.get('date'),
              debut: item.get('debut'),
              lieu: lieux.getIn([item.get('lieu'), 'description'], '?'),
              marqueur: item.get('marqueur'),
              marqueur_confirme: item.get('marqueur_confirme'),
              cl1,
              cl2,
              cl12
            }
          })
          .toList()
          .toJS()

      })
    );

    if (state.matchs.get('liste').size === 0 || state.matchs.get('maj') + 1800000 < (new Date().getTime())) {
      this.matchsAct.getAll();
    }

  }

  ngOnDestroy() {
    this.subs.forEach(s => s.unsubscribe());
  }

  tr(tag, data = {}) {
    return this.trServ.instant(tag, data);
  }

  textDate(item: Match) {
    return moment(item.date + ' ' + item.debut).format('ddd D MMM HH:mm');
  }

  doRefresh(refresher) {
    this.refresher = refresher;
    this.matchsAct.getAll();
  }

}
