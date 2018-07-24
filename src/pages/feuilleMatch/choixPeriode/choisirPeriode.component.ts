import { Component, EventEmitter, OnInit, OnDestroy } from '@angular/core';
import { List } from 'immutable';
import { NavController, NavParams } from 'ionic-angular';
import { Subscription } from 'rxjs';
import { NgRedux, select } from '@angular-redux/store';
import { FmService } from '../../../services/fmService';
import { evFactory } from '../../../store/mesMatchs/mesMatchs.initial-state';
import { FeuilleMatchRecord } from '../../../store/mesMatchs/mesMatchs.types';
import { IAppState } from '../../../store/store';
import * as _ from 'lodash';
import * as R from 'ramda';
import { UnePeriodeComponent } from '../../unePeriode/unePeriode.component';
import { EditeFinPeriodeComponent } from '../editeFinPeriode/editeFinPeriode.component';
import { FusilladeComponent } from '../fusillade/fusillade.component';

@Component(
  {
    templateUrl: './choisirPeriodeComponent.html'
  }
)
export class ChoisirPeriodeComponent implements OnInit, OnDestroy {

  public subs: Subscription[] = [];
  public idMatch: string;
  public editable = false;

  public idMatchChoisi;

  public periodes: number[] = [];
  public fusilladeExiste: boolean = false;


  @select(['mesMatchs', 'idMatch']) $idMatch;
  @select(['mesMatchs', 'idMatchCharge']) $idMatchCharge;
  @select(['mesMatchs', 'feuilleMatch']) $fm;
  @select(['mesMaths', 'joueurs']) $joueurs;

  constructor(
    public store: NgRedux<IAppState>,
    public fmServ: FmService,
    public navCtrl: NavController,
    public params: NavParams
  ) {

  }

  ngOnInit() {
    this.editable = this.params.get('editable');
    console.log('editable choisir periode', this.editable);
    this.subs.push(
      this.$idMatchCharge.subscribe(
        id => this.idMatch = id
      ),
      this.$idMatch.subscribe(id => this.idMatchChoisi = id)
      ,
      this.$idMatch
        .merge(this.$idMatchCharge)
        .debounceTime(100)
        .subscribe(() => {
          if (this.idMatch !== this.idMatchChoisi) {
            this.refresh()
          }
        })
      ,
      this.$fm.subscribe((events: List<FeuilleMatchRecord>) => {
        this.periodes = [];
        this.fusilladeExiste = false;
        events
          .filter(ev => ev.get('type_enreg') === 'fin_periode')
          .forEach(ev => this.periodes.push(ev.get('periode')));
        this.periodes = R.pipe(
          R.uniq,
          R.sortBy(R.identity)
        )(this.periodes) as number[];
        this.fusilladeExiste = !!events.find(ev => ev.get('type_enreg') === 'fusillade');
      })
    );
  }

  ngOnDestroy() {
    this.subs.forEach(s => s.unsubscribe());
  }


  async refresh() {
    console.log('refresh.......', this.idMatchChoisi);
    this.periodes = [];

    await this.fmServ.getFeuilleMatch(this.idMatchChoisi);
    console.log('fini');
  }

  nouvPeriode() {
    if (this.periodes.length == 0) {
      return 1;
    }
    return R.last(this.periodes) + 1;
  }

  ajouter(nouv: number) {
    this.navCtrl.push(EditeFinPeriodeComponent, {ev: null, nouv});
  }

  async choisir(noPer: number | string) {
    if (noPer === 'fusillade') {
      this.fmServ.periodeChoisie = null;
      let ev = this.store.getState().mesMatchs.get('feuilleMatch').find(ev => ev.get('type_enreg') === 'fusillade');
      if (!ev) {
        let ev = evFactory({
          id_match: this.idMatch,
          type_enreg: 'fusillade'
        });
        if (! await this.fmServ.sauvegarderEv(ev)) {
          return;
        }

        this.fmServ.currentEv = ev;
      } else {
        this.fmServ.currentEv = ev.toJS();
      }

      this.navCtrl.push(FusilladeComponent, {ev: this.fmServ.currentEv, editable: this.editable})
    } else {
      this.fmServ.periodeChoisie = noPer as number;
      this.navCtrl.push(UnePeriodeComponent, {idMatch: this.idMatch, editable: this.editable});
    }
  }
}
