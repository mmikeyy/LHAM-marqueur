import { Component, EventEmitter, OnInit, OnDestroy } from '@angular/core';
import { NavParams, ViewController } from 'ionic-angular';
import { Subscription } from 'rxjs';
import { TranslateService } from '@ngx-translate/core';
import { NgRedux, select } from '@angular-redux/store';
import { MatchsService } from '../../services/matchs.service';
import { Match } from '../../store/matchs';
import { MesMatchsActions } from '../../store/mesMatchs/mesMatchs.actions';
import { IAppState } from '../../store/store';
// import * as _ from 'lodash';
import * as R from 'ramda';
// import * as moment from 'moment';

@Component(
  {
    templateUrl: './reglerPointage.html'
  }
)
export class ReglerPointage implements OnInit, OnDestroy {

  public subs: Subscription[] = [];
  public idMatch: string = '';
  public match: Match;
  public ptsInit1;
  public ptsInit2;
  public videInit = true;

  @select(['mesMatchs', 'idMatch']) $idMatch;
  @select(['mesMatchs', 'liste']) $liste;

  constructor(
    public store: NgRedux<IAppState>,
    public trServ: TranslateService,
    public matchsServ: MatchsService,
    public params: NavParams,
    public viewCtrl: ViewController,
    public mesMatchsAct: MesMatchsActions
  ) {
    this.idMatch = this.params.get('id');
    if (this.idMatch) {
      let match = this.store.getState().mesMatchs.getIn(['liste', this.idMatch]);
      if (match) {
        this.match = match.toJS() as Match;
        this.ptsInit1 = match.pts1 || 0;
        this.ptsInit2 = match.pts2 || 0;
        this.videInit = match.pts1 === null && match.pts2 === null;
      }
    }
  }

  ngOnInit() {
    let nb = 0;
    this.subs.push(
      this.$liste.subscribe(() => {
        if (nb++){
          this.dismiss()
        }
      })
    );
  }

  ngOnDestroy() {

  }

  dismiss() {
    this.viewCtrl.dismiss();
  }

  accepter() {
    this.mesMatchsAct.updatePointage(this.idMatch, this.match.pts1, this.match.pts2);

  }
}
